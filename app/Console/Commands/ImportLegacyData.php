<?php

namespace App\Console\Commands;

use App\Models\Fiscalia;
use App\Models\Juzgado;
use App\Models\OtroSeguimiento;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportLegacyData extends Command
{
    protected $signature = 'netley:import-legacy';

    protected $description = 'Importa los datos del sistema legacy (conexión "legacy") al esquema normalizado de NETLEY Filament';

    /** Conteo de filas importadas/omitidas por tabla, para el reporte final. */
    protected array $reporte = [];

    /** area => [delito_normalizado => id], precargado tras importar delitos. */
    protected array $delitosIndex = [];

    protected const AREAS_VALIDAS = ['CIVIL', 'PENAL', 'FAMILIA', 'LABORAL'];

    public function handle(): int
    {
        if (! Schema::connection('legacy')->hasTable('delitos')) {
            $this->error('La conexión "legacy" no tiene datos. Importa primero el dump SQL en la base de staging (netley_legacy_staging).');

            return self::FAILURE;
        }

        $this->info('Importando datos legacy -> esquema normalizado...');

        // Nota: no se envuelve en DB::transaction() porque varios pasos ejecutan DDL
        // (ALTER TABLE ... AUTO_INCREMENT, SET FOREIGN_KEY_CHECKS), que en MySQL hace
        // commit implícito y rompe una transacción externa. El comando trunca las
        // tablas al inicio, así que es seguro y reejecutable si algo falla a mitad.
        $this->truncarTablasNuevas();

        $this->importarDelitos();
        $this->importarPersonal();
        $this->importarUsers();
        $this->importarClientes();
        $this->importarCasos();
        $this->importarConsultas();
        $this->importarCasoPersonal();
        $this->importarRespuestas();
        $this->importarCitas();
        $this->importarCitaPersonal();
        $this->importarLlamadas();
        $this->importarLlamadaIntentos();
        $this->importarTalleres();
        $this->importarPagos();
        $this->importarPlanesPago();
        $this->importarInformesCierre();
        $this->importarInformesCita();
        $this->importarContactos();
        $this->importarDocumentosPersonal();
        $this->importarDocumentosCliente();
        $this->importarFiscalias();
        $this->importarJuzgados();
        $this->importarOtrosSeguimientos();
        $this->importarSeguimientosCaso();
        $this->importarSeguimientosAgendados();
        $this->importarTestimonios();
        $this->importarMunicipios();
        $this->importarOficinas();

        $this->table(['Tabla', 'Importadas', 'Omitidas', 'Nota'], collect($this->reporte)->map(fn ($r, $tabla) => [
            $tabla, $r['ok'] ?? 0, $r['omitidas'] ?? 0, $r['nota'] ?? '',
        ])->values()->toArray());

        $this->info('Importación completada.');

        return self::SUCCESS;
    }

    protected function truncarTablasNuevas(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tablas = [
            'seguimientos_agendados', 'seguimientos_caso', 'otros_seguimientos', 'juzgados', 'fiscalias',
            'documentos_cliente', 'documentos_personal', 'contacto_personal', 'contactos',
            'informes_cita', 'informes_cierre_caso', 'planes_pago', 'pagos',
            'llamada_intentos', 'llamadas', 'talleres', 'cita_personal', 'citas',
            'respuestas', 'caso_personal', 'consultas', 'casos', 'clientes',
            'users', 'personal', 'delitos', 'testimonios', 'municipios', 'oficinas',
        ];

        foreach ($tablas as $tabla) {
            DB::table($tabla)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function marcar(string $tabla, int $ok, int $omitidas = 0, string $nota = ''): void
    {
        $this->reporte[$tabla] = ['ok' => $ok, 'omitidas' => $omitidas, 'nota' => $nota];
    }

    // ---------------------------------------------------------------
    // Catálogos base
    // ---------------------------------------------------------------

    protected function importarDelitos(): void
    {
        $filas = DB::connection('legacy')->table('delitos')->orderBy('idd')->get();

        $rows = $filas->map(fn ($d) => [
            'id' => $d->idd,
            'area' => $this->normalizarArea($d->area) ?? Str::upper(trim($d->area)),
            'delito' => trim($d->delito),
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        DB::table('delitos')->insert($rows);
        $this->ajustarAutoIncrement('delitos', $filas->max('idd') ?? 0);

        foreach ($rows as $r) {
            $this->delitosIndex[$r['area']][$this->normalizarTexto($r['delito'])] = $r['id'];
        }

        $this->marcar('delitos', count($rows));
    }

    protected function importarPersonal(): void
    {
        $filas = DB::connection('legacy')->table('personal')->orderBy('idper')->get();

        $rows = $filas->map(function ($p) {
            // Nota: personal.especialidad es la especialidad PROFESIONAL del
            // staff (legal, psicología, médica, trabajo social — ver
            // App\Support\PersonalEspecialidades), no la "materia legal" de
            // Casos/Delitos, así que normalizarArea() (que solo reconoce
            // CIVIL/PENAL/FAMILIA/LABORAL) no aplica aquí: descartaba en
            // silencio cualquier especialidad médica/psicológica/etc.
            $especialidades = collect(explode(',', (string) $p->especialidad))
                ->map(fn ($e) => Str::upper(trim($e)))
                ->filter(fn ($e) => $e !== '' && $e !== 'NO APLICA')
                ->unique()
                ->values()
                ->toArray();

            return [
                'id' => $p->idper,
                'rol' => in_array((int) $p->categoria, [0, 1, 2, 3, 4, 5], true) ? (int) $p->categoria : 5,
                'nombres' => trim($p->nombres),
                'ap_paterno' => trim($p->ap_paterno),
                'ap_materno' => trim($p->ap_materno) ?: null,
                'genero' => $p->genero ?: null,
                'ci' => trim($p->ci_per),
                'ci_expedido' => $p->expedido ?: null,
                'fecha_nacimiento' => $this->fecha($p->fecha_nacimiento),
                'nacionalidad' => $p->nacionalidad ?: null,
                'direccion' => $p->direccion ?: null,
                'telefono' => $p->telf ? (string) $p->telf : null,
                'whatsapp' => $p->whatsapp ? (string) $p->whatsapp : null,
                'correo' => $p->correo ?: null,
                'estado' => in_array($p->estado, ['habilitado', 'inhabilitado'], true) ? $p->estado : 'habilitado',
                'cargo' => $p->cargo ?: null,
                'profesion' => $p->profession ?: null,
                'especialidades' => json_encode($especialidades),
                'tiene_contrato' => (bool) $p->contrato,
                'foto' => $p->foto ?: null,
                'ciudad_residencia' => $p->ciudad_res ?: null,
                'estado_civil' => $p->estado_civil ?: null,
                'fecha_inicio' => $this->fechaHora($p->fecha_inicio),
                'fecha_fin' => $this->fecha($p->fecha_fin),
                'motivo_baja' => $p->motivo ?: null,
                'nota' => $p->nota_netley ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        DB::table('personal')->insert($rows);
        $this->ajustarAutoIncrement('personal', $filas->max('idper') ?? 0);

        $this->marcar('personal', count($rows));
    }

    protected function importarUsers(): void
    {
        $filas = DB::connection('legacy')->table('users')
            ->leftJoin('asignacion_credenciales', 'asignacion_credenciales.id', '=', 'users.id')
            ->select('users.id', 'users.username', 'users.password', 'asignacion_credenciales.id_per')
            ->orderBy('users.id')
            ->get();

        $rows = $filas->map(fn ($u) => [
            'id' => $u->id,
            'personal_id' => $u->id_per,
            'name' => $u->username,
            'username' => $u->username,
            'email' => $u->id_per ? "{$u->username}@netley.local" : "{$u->username}+{$u->id}@netley.local",
            'password' => Hash::make($u->password),
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        DB::table('users')->insert($rows);
        $this->ajustarAutoIncrement('users', $filas->max('id') ?? 0);

        $sinPersonal = $filas->whereNull('id_per')->count();
        $this->marcar('users', count($rows), 0, $sinPersonal ? "{$sinPersonal} sin personal vinculado" : '');
    }

    // ---------------------------------------------------------------
    // CRM núcleo
    // ---------------------------------------------------------------

    protected function importarClientes(): void
    {
        $filas = DB::connection('legacy')->table('cliente_ejecutivo')->orderBy('id_cliente')->get();

        $rows = $filas->map(fn ($c) => [
            'id' => $c->id_cliente,
            'nombres' => trim($c->nombres_cli),
            'ap_paterno' => trim($c->ap_pat_cli),
            'ap_materno' => trim($c->ap_mat_cli) ?: null,
            'telefono' => $c->telf_cli ? (string) $c->telf_cli : null,
            'whatsapp' => $c->wath_cli ? (string) $c->wath_cli : null,
            'correo' => $c->correo_cli ?: null,
            'ci' => trim($c->ci_cli),
            'extension' => $c->extencion ?: null,
            'sucursal' => $c->sucursal ?: 'LA PAZ',
            'direccion' => $c->direccion_cli ?: null,
            'nro_casos' => (int) $c->nro_casos,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        DB::table('clientes')->insert($rows);
        $this->ajustarAutoIncrement('clientes', $filas->max('id_cliente') ?? 0);

        $this->marcar('clientes', count($rows));
    }

    protected function importarCasos(): void
    {
        $filas = DB::connection('legacy')->table('caso')
            ->join('caso_cliente', 'caso_cliente.id_caso', '=', 'caso.id_caso')
            ->select('caso.*', 'caso_cliente.id_cliente')
            ->orderBy('caso.id_caso')
            ->get();

        $omitidas = 0;
        $rows = [];

        foreach ($filas as $c) {
            if (! DB::table('clientes')->where('id', $c->id_cliente)->exists()) {
                $omitidas++;

                continue;
            }

            $area = $this->normalizarArea($c->especialidad) ?? 'CIVIL';
            $delitoId = $this->buscarDelitoId($area, $c->delito);

            $rows[] = [
                'id' => $c->id_caso,
                'cliente_id' => $c->id_cliente,
                'especialidad' => $area,
                'delito_id' => $delitoId,
                'delito_texto' => $delitoId ? null : (trim((string) $c->delito) ?: null),
                'descripcion' => $c->descripcion ?: null,
                'apersonamiento' => $c->apersonamiento ?: null,
                'iguala' => $c->iguala ?: 0,
                'saldo' => $c->saldo ?: 0,
                'pagado' => $c->pagado ?: 0,
                'fecha_inicio' => $this->fecha($c->fecha_inicio) ?? now()->toDateString(),
                'fecha_fin' => $this->fecha($c->fecha_fin),
                'estado' => $this->normalizarEstadoCaso($c->estado_caso),
                'ciudad' => $c->ciudad_caso ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('casos')->insert($rows);
        $this->ajustarAutoIncrement('casos', $filas->max('id_caso') ?? 0);

        $this->marcar('casos', count($rows), $omitidas, $omitidas ? 'sin cliente válido en caso_cliente' : '');
    }

    protected function importarConsultas(): void
    {
        $filas = DB::connection('legacy')->table('consulta')->orderBy('id_con')->get();

        $rows = $filas->map(fn ($c) => [
            'id' => $c->id_con,
            'caso_id' => null, // el legacy solo guardaba una etiqueta de texto ("Caso #N"), no fiable como FK
            'nombres' => trim($c->nombres_con),
            'ap_paterno' => $c->ap_pat ?: null,
            'ap_materno' => $c->ap_mat ?: null,
            'telefono' => $c->telf ?: null,
            'whatsapp' => $c->whatsapp ?: null,
            'correo' => $c->correo ?: null,
            'consulta' => $c->consul,
            'nota_interna' => $c->nota_interna ?: null,
            'fecha_consulta' => $this->fechaHora($c->fecha_con) ?? now(),
            'pais' => $c->pais ?: null,
            'ciudad' => $c->ciudad ?: null,
            'provincia' => $c->provincia ?: null,
            'direccion' => $c->direccion ?: null,
            'estado' => $this->normalizarEstadoConsulta($c->estado, $c->paso),
            'fecha_contacto' => $this->fecha($c->fecha_contacto),
            'origen' => $c->origen ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        DB::table('consultas')->insert($rows);
        $this->ajustarAutoIncrement('consultas', $filas->max('id_con') ?? 0);

        $this->marcar('consultas', count($rows));
    }

    protected function importarCasoPersonal(): void
    {
        $filas = DB::connection('legacy')->table('asignacion_personal')->get();

        $omitidas = 0;
        $rows = [];
        $vistos = [];

        foreach ($filas as $a) {
            $casoOk = DB::table('casos')->where('id', $a->id_caso)->exists();
            $perOk = DB::table('personal')->where('id', $a->id_per)->exists();
            $clave = "{$a->id_caso}-{$a->id_per}";

            if (! $casoOk || ! $perOk || isset($vistos[$clave])) {
                $omitidas++;

                continue;
            }

            $vistos[$clave] = true;
            $rows[] = [
                'caso_id' => $a->id_caso,
                'personal_id' => $a->id_per,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows) {
            DB::table('caso_personal')->insert($rows);
        }

        $this->marcar('caso_personal', count($rows), $omitidas);
    }

    protected function importarRespuestas(): void
    {
        $filas = DB::connection('legacy')->table('respuesta')
            ->join('cons_resp', 'cons_resp.id_resp', '=', 'respuesta.id_resp')
            ->select('respuesta.*', 'cons_resp.id_con', 'cons_resp.idper')
            ->orderBy('respuesta.id_resp')
            ->get();

        $sinCoincidencia = DB::connection('legacy')->table('respuesta')->count() - $filas->count();

        $rows = $filas->map(function ($r) {
            $area = $this->normalizarArea($r->designacion);
            $delitoId = $area ? $this->buscarDelitoId($area, $r->delito) : null;

            return [
                'id' => $r->id_resp,
                'consulta_id' => $r->id_con,
                'personal_id' => DB::table('personal')->where('id', $r->idper)->exists() ? $r->idper : null,
                'respuesta' => $r->resp,
                'paso' => $r->paso_resp ?: 'respondido',
                'designacion' => $area,
                'delito_id' => $delitoId,
                'delito_texto' => $delitoId ? null : ($r->delito ?: null),
                'publicado' => Str::lower((string) $r->publicacion) === 'si',
                'fecha_respuesta' => $this->fechaHora($r->fecha_resp) ?? now(),
                'nota' => $r->nota ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        DB::table('respuestas')->insert($rows);
        $this->ajustarAutoIncrement('respuestas', collect($rows)->max('id') ?? 0);

        $this->marcar('respuestas', count($rows), $sinCoincidencia, $sinCoincidencia ? 'sin fila en cons_resp (huérfanas)' : '');
    }

    // ---------------------------------------------------------------
    // Agenda / llamadas
    // ---------------------------------------------------------------

    protected function importarCitas(): void
    {
        $filas = DB::connection('legacy')->table('cita_cons')->orderBy('id_cita_cons')->get();
        $consultaPorCita = DB::connection('legacy')->table('cons_citas')->pluck('id_con', 'id_cita_cons');
        $casosIds = DB::table('casos')->pluck('id')->flip();

        $rows = $filas->map(function ($c) use ($consultaPorCita, $casosIds) {
            $casoId = null;

            if (preg_match('/(\d+)/', (string) $c->detalle, $m) && isset($casosIds[(int) $m[1]])) {
                $casoId = (int) $m[1];
            }

            return [
                'id' => $c->id_cita_cons,
                'consulta_id' => $consultaPorCita[$c->id_cita_cons] ?? null,
                'caso_id' => $casoId,
                'fecha' => $this->fecha($c->fehca_cita_cons) ?? now()->toDateString(),
                'hora' => $c->hora_cita_cons,
                'detalle' => $c->detalle ?: null,
                'tipo' => $c->tipo ?: null,
                'origen' => $c->origen ?: null,
                'anulada' => Str::contains(Str::upper((string) $c->detalle), 'ANULADA'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        DB::table('citas')->insert($rows);
        $this->ajustarAutoIncrement('citas', $filas->max('id_cita_cons') ?? 0);

        $this->marcar('citas', count($rows));
    }

    protected function importarCitaPersonal(): void
    {
        $filas = DB::connection('legacy')->table('cita_per')->get();

        $rows = $filas->filter(fn ($c) => DB::table('personal')->where('id', $c->idper)->exists()
                && DB::table('citas')->where('id', $c->id_cita_cons)->exists())
            ->map(fn ($c) => [
                'cita_id' => $c->id_cita_cons,
                'personal_id' => $c->idper,
                'created_at' => now(),
                'updated_at' => now(),
            ])->unique(fn ($r) => "{$r['cita_id']}-{$r['personal_id']}")->values()->toArray();

        if ($rows) {
            DB::table('cita_personal')->insert($rows);
        }

        $this->marcar('cita_personal', count($rows));
    }

    protected function importarLlamadas(): void
    {
        // agenda_llamadas está vacía en el dump legacy (0 filas) — se deja la tabla lista para uso futuro.
        $total = DB::connection('legacy')->table('agenda_llamadas')->count();
        $this->marcar('llamadas', 0, $total, 'agenda_llamadas no tenía filas en el dump legacy');
    }

    protected function importarLlamadaIntentos(): void
    {
        $total = DB::connection('legacy')->table('llamadas_intentos')->count();
        // Referencian agenda_llamadas, que está vacía: no hay llamada válida a la que enlazarlas.
        $this->marcar('llamada_intentos', 0, $total, $total ? 'huérfanas: agenda_llamadas vacía' : '');
    }

    protected function importarTalleres(): void
    {
        $filas = DB::connection('legacy')->table('talleres')->orderBy('id_taller')->get();

        $rows = $filas->map(function ($t) {
            $area = $this->normalizarArea($t->materia_legal);
            $delitoId = $area ? $this->buscarDelitoId($area, $t->delito_denuncia) : null;

            return [
                'fecha_programada' => $this->fecha($t->fecha_programada),
                'hora_programada' => $t->hora_programada,
                'nombres' => $t->nombres ?: null,
                'ap_paterno' => $t->apellido_pat ?: null,
                'ap_materno' => $t->apellido_mat ?: null,
                'telefono' => $t->telefono ?: null,
                'whatsapp' => $t->whatsapp ?: null,
                'ciudad' => $t->ciudad ?: null,
                'motivo' => $t->motivo ?: null,
                'numero_consulta' => $t->numero_consulta ?: null,
                'materia_legal' => $area,
                'delito_id' => $delitoId,
                'delito_texto' => $delitoId ? null : ($t->delito_denuncia ?: null),
                'consulta' => $t->consulta ?: null,
                'respuesta' => $t->respuesta ?: null,
                'creado_por' => $t->creado_por ?: null,
                'personal_texto' => $t->personal ?: null,
                'abogado_texto' => $t->abogado ?: null,
                'origen' => $t->origen ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        if ($rows) {
            DB::table('talleres')->insert($rows);
        }

        $this->marcar('talleres', count($rows));
    }

    // ---------------------------------------------------------------
    // Pagos
    // ---------------------------------------------------------------

    protected function importarPagos(): void
    {
        $filas = DB::connection('legacy')->table('pago')
            ->join('pago_caso', 'pago_caso.id_pago', '=', 'pago.id_pago')
            ->join('pago_cliente', 'pago_cliente.ip_pago', '=', 'pago.id_pago')
            ->select('pago.*', 'pago_caso.id_caso', 'pago_cliente.id_cliente')
            ->orderBy('pago.id_pago')
            ->get();

        $omitidas = DB::connection('legacy')->table('pago')->count() - $filas->count();

        $rows = $filas->map(fn ($p) => [
            'id' => $p->id_pago,
            'caso_id' => $p->id_caso,
            'cliente_id' => $p->id_cliente,
            'monto' => $p->monto,
            'fecha_pago' => $this->fecha($p->fecha_pago) ?? now()->toDateString(),
            'nro_cuota' => (int) $p->nro_cuota,
            'sucursal' => $p->sucursal ?: 'LA PAZ',
            'nro_recibo' => (int) $p->nro_recibo,
            'registrado_por' => $p->registrado_por ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        DB::table('pagos')->insert($rows);
        $this->ajustarAutoIncrement('pagos', $filas->max('id_pago') ?? 0);

        $this->marcar('pagos', count($rows), $omitidas, $omitidas ? 'sin caso/cliente asociado' : '');
    }

    protected function importarPlanesPago(): void
    {
        $filas = DB::connection('legacy')->table('plan_pagos')->orderBy('id_plan')->get();

        $rows = $filas->filter(fn ($p) => DB::table('casos')->where('id', $p->id_caso)->exists())
            ->map(fn ($p) => [
                'id' => $p->id_plan,
                'caso_id' => $p->id_caso,
                'numero' => (int) $p->numero,
                'fecha' => $this->fecha($p->fecha) ?? now()->toDateString(),
                'monto' => $p->monto,
                'nuevo_saldo' => $p->nuevo_saldo,
                'estado' => in_array($p->estado, ['pendiente', 'pagado'], true) ? $p->estado : 'pendiente',
                'creado_por' => $p->creado_por ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->toArray();

        if ($rows) {
            DB::table('planes_pago')->insert($rows);
            $this->ajustarAutoIncrement('planes_pago', $filas->max('id_plan') ?? 0);
        }

        $this->marcar('planes_pago', count($rows), $filas->count() - count($rows));
    }

    // ---------------------------------------------------------------
    // Informes
    // ---------------------------------------------------------------

    protected function importarInformesCierre(): void
    {
        $filas = DB::connection('legacy')->table('informe_cierre_caso')->orderBy('id_informe')->get();

        $rows = $filas->filter(fn ($i) => DB::table('casos')->where('id', $i->id_caso)->exists())
            ->map(fn ($i) => [
                'id' => $i->id_informe,
                'caso_id' => $i->id_caso,
                'cliente_id' => $i->id_cliente && DB::table('clientes')->where('id', $i->id_cliente)->exists() ? $i->id_cliente : null,
                'resultado' => $i->resultado ?: null,
                'saldo' => $i->saldo ?: 0,
                'perdida' => $i->perdida ?: 0,
                'asume' => $i->asume ?: null,
                'seguimiento_responsable' => $i->seguimiento_responsable ?: null,
                'opciones' => $i->opciones ?: null,
                'nota_netley' => $i->nota_netley ?: null,
                'creado_por' => $i->creado_por ?: null,
                'fecha_cierre' => $this->fecha($i->fecha_cierre),
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->toArray();

        if ($rows) {
            DB::table('informes_cierre_caso')->insert($rows);
            $this->ajustarAutoIncrement('informes_cierre_caso', $filas->max('id_informe') ?? 0);
        }

        $this->marcar('informes_cierre_caso', count($rows));
    }

    protected function importarInformesCita(): void
    {
        $filas = DB::connection('legacy')->table('informe_cita')->orderBy('id_infome')->get();

        $rows = $filas->filter(fn ($i) => DB::table('citas')->where('id', $i->id_cita)->exists())
            ->map(fn ($i) => [
                'id' => $i->id_infome,
                'cita_id' => $i->id_cita,
                'fecha_informe' => $this->fecha($i->fecha_informe) ?? now()->toDateString(),
                'detalle' => $i->detalle_informe ?: null,
                'forma_ingreso' => $i->forma_ingreso ?: null,
                'nombre_colegio' => $i->nombre_colegio ?: null,
                'telefono' => $i->telefono ?: null,
                'nombres' => $i->nombres ?: null,
                'apellidos' => $i->apellidos ?: null,
                'responsable' => $i->responsable ?: null,
                'acciones' => $i->acciones ?: null,
                'anticipo' => $i->anticipo ?: 0,
                'iguala' => $i->iguala ?: 0,
                'comision_bs' => $i->comicionbs ?: null,
                'comision_pct' => $i->comicionp ?: null,
                'redaccion' => $i->redaccion ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->toArray();

        if ($rows) {
            DB::table('informes_cita')->insert($rows);
            $this->ajustarAutoIncrement('informes_cita', $filas->max('id_infome') ?? 0);
        }

        $this->marcar('informes_cita', count($rows));
    }

    // ---------------------------------------------------------------
    // Contactos, documentos, seguimiento judicial
    // ---------------------------------------------------------------

    protected function importarContactos(): void
    {
        $filas = DB::connection('legacy')->table('contactos')->orderBy('id_contacto')->get();

        $rows = $filas->map(fn ($c) => [
            'id' => $c->id_contacto,
            'institucion' => $c->institucion ?: null,
            'entidad' => $c->entidad ?: null,
            'unidad' => $c->unidad ?: null,
            'cargo' => $c->cargo ?: null,
            'profesion' => $c->profesion ?: null,
            'nombre' => $c->nombre,
            'apellido' => $c->apellido ?: null,
            'direccion' => $c->direccion ?: null,
            'zona' => $c->zona ?: null,
            'ciudad' => $c->ciudad ?: null,
            'correo' => $c->correo ?: null,
            'telefono' => $c->telefono ?: null,
            'horario_contacto' => $c->horario_contacto ?: null,
            'nota' => $c->nota ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        if ($rows) {
            DB::table('contactos')->insert($rows);
            $this->ajustarAutoIncrement('contactos', $filas->max('id_contacto') ?? 0);
        }

        $this->marcar('contactos', count($rows));

        $pivote = DB::connection('legacy')->table('contacto_per')->get()
            ->filter(fn ($c) => DB::table('personal')->where('id', $c->idper)->exists()
                && DB::table('contactos')->where('id', $c->id_contacto)->exists())
            ->map(fn ($c) => [
                'contacto_id' => $c->id_contacto,
                'personal_id' => $c->idper,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->toArray();

        if ($pivote) {
            DB::table('contacto_personal')->insert($pivote);
        }

        $this->marcar('contacto_personal', count($pivote));
    }

    protected function importarDocumentosPersonal(): void
    {
        $filas = DB::connection('legacy')->table('docper')->orderBy('id_docper')->get();

        $rows = $filas->filter(fn ($d) => DB::table('personal')->where('id', $d->documento)->exists())
            ->map(fn ($d) => [
                'id' => $d->id_docper,
                'personal_id' => $d->documento,
                'nombre' => $d->archivo,
                'ruta' => $d->direccion,
                'fecha' => $this->fecha($d->fecha_docper),
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->toArray();

        if ($rows) {
            DB::table('documentos_personal')->insert($rows);
            $this->ajustarAutoIncrement('documentos_personal', $filas->max('id_docper') ?? 0);
        }

        $this->marcar('documentos_personal', count($rows), $filas->count() - count($rows));
    }

    protected function importarDocumentosCliente(): void
    {
        $filas = DB::connection('legacy')->table('doc_cli')->orderBy('id_doc_cli')->get();

        $rows = $filas->filter(fn ($d) => DB::table('clientes')->where('id', $d->id_cli)->exists())
            ->map(fn ($d) => [
                'id' => $d->id_doc_cli,
                'cliente_id' => $d->id_cli,
                'caso_id' => DB::table('casos')->where('id', $d->caso)->exists() ? $d->caso : null,
                'ruta' => $d->direccion_doc_cli,
                'descripcion' => $d->descripcion ?: null,
                'fecha_origen' => $this->fechaHora($d->fecha_origen) ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->toArray();

        if ($rows) {
            DB::table('documentos_cliente')->insert($rows);
            $this->ajustarAutoIncrement('documentos_cliente', $filas->max('id_doc_cli') ?? 0);
        }

        $this->marcar('documentos_cliente', count($rows), $filas->count() - count($rows));
    }

    protected function importarFiscalias(): void
    {
        $filas = DB::connection('legacy')->table('fiscalias')->orderBy('id_fiscalia')->get();

        $rows = $filas->filter(fn ($f) => DB::table('casos')->where('id', $f->id_caso)->exists())
            ->map(fn ($f) => [
                'id' => $f->id_fiscalia,
                'caso_id' => $f->id_caso,
                'fecha_inicio' => $this->fecha($f->datos_inicio),
                'fecha_fin' => $this->fecha($f->datos_fin),
                'vigente' => Str::lower((string) $f->vigente) === 'si',
                'num_caso' => $f->num_caso ?: null,
                'fiscalia_num' => $f->fiscalia_num ?: null,
                'nombre_fiscal' => $f->nombre_fiscal ?: null,
                'telefono_fiscal' => $f->telefono_fiscal ?: null,
                'investigador' => $f->investigador ?: null,
                'telefono_investigador' => $f->telefono_investigador ?: null,
                'auxiliar' => $f->auxiliar ?: null,
                'telefono_auxiliar' => $f->telefono_auxiliar ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->toArray();

        if ($rows) {
            DB::table('fiscalias')->insert($rows);
            $this->ajustarAutoIncrement('fiscalias', $filas->max('id_fiscalia') ?? 0);
        }

        $this->marcar('fiscalias', count($rows));
    }

    protected function importarJuzgados(): void
    {
        $filas = DB::connection('legacy')->table('juzgados')->orderBy('id_juzgado')->get();

        $rows = $filas->filter(fn ($j) => DB::table('casos')->where('id', $j->id_caso)->exists())
            ->map(fn ($j) => [
                'id' => $j->id_juzgado,
                'caso_id' => $j->id_caso,
                'fecha_inicio' => $this->fecha($j->datos_inicio),
                'fecha_fin' => $this->fecha($j->datos_fin),
                'vigente' => Str::lower((string) $j->vigente) === 'si',
                'num_caso' => $j->num_caso ?: null,
                'juzgado_num' => $j->juzgado_num ?: null,
                'nombre_juez' => $j->nombre_juez ?: null,
                'telefono_juez' => $j->telefono_juez ?: null,
                'secretaria' => $j->secretaria ?: null,
                'telefono_secretaria' => $j->telefono_secretaria ?: null,
                'auxiliar' => $j->auxiliar ?: null,
                'telefono_auxiliar' => $j->telefono_auxiliar ?: null,
                'oficial' => $j->oficial ?: null,
                'telefono_oficial' => $j->telefono_oficial ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->toArray();

        if ($rows) {
            DB::table('juzgados')->insert($rows);
            $this->ajustarAutoIncrement('juzgados', $filas->max('id_juzgado') ?? 0);
        }

        $this->marcar('juzgados', count($rows));
    }

    protected function importarOtrosSeguimientos(): void
    {
        $filas = DB::connection('legacy')->table('otro_seguimiento')->orderBy('id_otro_seguimiento')->get();

        $rows = $filas->filter(fn ($o) => DB::table('casos')->where('id', $o->id_caso)->exists())
            ->map(fn ($o) => [
                'id' => $o->id_otro_seguimiento,
                'caso_id' => $o->id_caso,
                'nombre_instancia' => $o->nombre_instancia ?: null,
                'fecha_inicio' => $this->fecha($o->datos_inicio),
                'fecha_fin' => $this->fecha($o->datos_fin),
                'num_caso' => $o->num_caso ?: null,
                'nombre_contacto' => $o->nombre_contacto ?: null,
                'numero_contacto' => $o->numero_contacto ?: null,
                'detalles' => $o->detalles ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->toArray();

        if ($rows) {
            DB::table('otros_seguimientos')->insert($rows);
            $this->ajustarAutoIncrement('otros_seguimientos', $filas->max('id_otro_seguimiento') ?? 0);
        }

        $this->marcar('otros_seguimientos', count($rows));
    }

    protected function importarSeguimientosCaso(): void
    {
        $filas = DB::connection('legacy')->table('seguimiento_caso')->orderBy('id_seguimiento')->get();

        $rows = $filas->filter(fn ($s) => DB::table('casos')->where('id', $s->id_caso)->exists())
            ->map(fn ($s) => [
                'id' => $s->id_seguimiento,
                'caso_id' => $s->id_caso,
                'fecha_seguimiento' => $this->fechaHora($s->fecha_seguimiento) ?? now(),
                'responsable' => $s->responsable ?: null,
                'etapa_proceso' => $s->etapa_proceso ?: null,
                'observaciones' => $s->observaciones ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->toArray();

        if ($rows) {
            DB::table('seguimientos_caso')->insert($rows);
            $this->ajustarAutoIncrement('seguimientos_caso', $filas->max('id_seguimiento') ?? 0);
        }

        $this->marcar('seguimientos_caso', count($rows));
    }

    protected function importarSeguimientosAgendados(): void
    {
        $filas = DB::connection('legacy')->table('seguimientos_agendados')->orderBy('id')->get();

        $mapaTipos = [
            'fiscalia' => Fiscalia::class,
            'juzgado' => Juzgado::class,
            'otro' => OtroSeguimiento::class,
        ];

        $omitidas = 0;
        $rows = [];

        foreach ($filas as $s) {
            $tipo = Str::lower(trim((string) $s->tipo_instancia));
            $clase = $mapaTipos[$tipo] ?? null;

            $instanciaExiste = $clase && DB::table((new $clase)->getTable())->where('id', $s->id_instancia)->exists();
            $casoOk = DB::table('casos')->where('id', $s->id_caso)->exists();
            $perOk = DB::table('personal')->where('id', $s->id_personal)->exists();

            if (! $instanciaExiste || ! $casoOk || ! $perOk) {
                $omitidas++;

                continue;
            }

            $rows[] = [
                'caso_id' => $s->id_caso,
                'personal_id' => $s->id_personal,
                'instancia_type' => $clase,
                'instancia_id' => $s->id_instancia,
                'fecha' => $this->fecha($s->fecha) ?? now()->toDateString(),
                'hora' => $s->hora,
                'detalles' => $s->detalles ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows) {
            DB::table('seguimientos_agendados')->insert($rows);
        }

        $this->marcar('seguimientos_agendados', count($rows), $omitidas);
    }

    // ---------------------------------------------------------------
    // Contenido público (moderación) y catálogos geográficos
    // ---------------------------------------------------------------

    protected function importarTestimonios(): void
    {
        $filas = DB::connection('legacy')->table('testimonios')->orderBy('id_testimonio')->get();

        $rows = $filas->map(fn ($t) => [
            'id' => $t->id_testimonio,
            'nombre' => $t->nombre,
            'testimonio' => $t->testimonio,
            'calificacion' => $t->calificacion ?? 5,
            'correo' => $t->correo ?: null,
            'fecha' => $this->fechaHora($t->fecha) ?? now(),
            'estado' => match ((int) $t->aprobado) {
                1 => 'aprobado',
                2 => 'rechazado',
                default => 'pendiente',
            },
            'visible' => (bool) $t->visible,
            'notas_admin' => $t->notas_admin ?: null,
            'fecha_modificacion' => $this->fechaHora($t->fecha_modificacion),
            'modificado_por' => $t->modificado_por ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        if ($rows) {
            DB::table('testimonios')->insert($rows);
            $this->ajustarAutoIncrement('testimonios', $filas->max('id_testimonio') ?? 0);
        }

        $this->marcar('testimonios', count($rows));
    }

    protected function importarMunicipios(): void
    {
        $filas = DB::connection('legacy')->table('municipios')->orderBy('idm')->get();

        $rows = $filas->map(fn ($m) => [
            'id' => $m->idm,
            'ciudad' => $m->ciudad,
            'municipio' => $m->municipio,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        if ($rows) {
            DB::table('municipios')->insert($rows);
            $this->ajustarAutoIncrement('municipios', $filas->max('idm') ?? 0);
        }

        $this->marcar('municipios', count($rows));
    }

    protected function importarOficinas(): void
    {
        $filas = DB::connection('legacy')->table('oficinas')->orderBy('ido')->get();

        $rows = $filas->map(fn ($o) => [
            'id' => $o->ido,
            'ciudad' => $o->ciudad,
            'oficina' => $o->oficina,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        if ($rows) {
            DB::table('oficinas')->insert($rows);
            $this->ajustarAutoIncrement('oficinas', $filas->max('ido') ?? 0);
        }

        $this->marcar('oficinas', count($rows));
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    protected function normalizarArea(?string $valor): ?string
    {
        $v = Str::upper(trim((string) $valor));

        return in_array($v, self::AREAS_VALIDAS, true) ? $v : null;
    }

    protected function normalizarTexto(string $valor): string
    {
        return Str::upper(trim($valor));
    }

    protected function buscarDelitoId(?string $area, ?string $delitoTexto): ?int
    {
        if (! $area || ! $delitoTexto) {
            return null;
        }

        return $this->delitosIndex[$area][$this->normalizarTexto($delitoTexto)] ?? null;
    }

    protected function normalizarEstadoCaso(?string $valor): string
    {
        $v = Str::upper(trim((string) $valor));

        return match (true) {
            $v === 'CERRADO' || $v === 'FINALIZADO' || $v === 'CONCLUIDO' => 'cerrado',
            $v === 'REACTIVADO-PENDIENTE' => 'reactivado_pendiente',
            default => 'activo_pendiente',
        };
    }

    protected function normalizarEstadoConsulta(?string $estado, ?string $paso): string
    {
        $v = Str::upper(trim((string) $estado));
        $p = Str::upper(trim((string) $paso));

        return match (true) {
            $v === 'REACTIVADO' => 'reactivado',
            Str::startsWith($v, 'CASO') => 'caso_iniciado',
            $v === 'PAGADO' => 'caso_iniciado',
            Str::contains($v, 'NO CONTESTA') => 'no_contesta',
            Str::contains($v, 'SOLO CONSULTA') => 'solo_consulta',
            Str::contains($v, 'PSICOLOGIA') => 'remitir_psicologia',
            Str::contains($v, 'SOCIAL') => 'remitir_social',
            $p === 'RESPONDIDO' => 'respondido',
            default => 'pendiente',
        };
    }

    protected function fecha(?string $valor): ?string
    {
        if (! $valor || Str::startsWith($valor, '0000-00-00')) {
            return null;
        }

        try {
            return Carbon::parse($valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function fechaHora(?string $valor): ?Carbon
    {
        if (! $valor || Str::startsWith($valor, '0000-00-00')) {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function ajustarAutoIncrement(string $tabla, int $maxId): void
    {
        DB::statement("ALTER TABLE `{$tabla}` AUTO_INCREMENT = ".($maxId + 1));
    }
}
