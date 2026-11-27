<?php

namespace App\Filament\Admin\Support;

use App\Enums\Rol;
use App\Models\ActuadoCaso;
use App\Models\Caso;
use App\Models\Personal;
use App\Models\SeguimientoCaso;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * "Seguimiento del proceso" (Datos judiciales del caso), en dos momentos:
 *
 * 1. Registrar seguimiento diario: fecha, abogada/procuradora, etapa e
 *    instancia (automáticas, se pueden cambiar) y la lista de "Actuados a
 *    realizar" (actuado, detalle, fecha, hora, alarma).
 * 2. Registrar actuados realizados (Informe): los mismos datos y la lista
 *    de actuados pendientes con "Actuado concluido" Sí/No. Uno no concluido
 *    sigue pendiente al día siguiente hasta concluirlo o eliminarlo (quitar
 *    su fila en el informe lo elimina).
 *
 * La etapa del proceso no la puede cambiar una procuradora salvo que tenga
 * el privilegio "Cambiar la etapa del proceso" (ver Privilegios).
 */
class SeguimientoProceso
{
    public const ACTUADOS = [
        'Seguimiento', 'Notificación', 'Informe', 'Memorial', 'Fotocopias',
        'Audiencia', 'Resolución / decreto', 'Revisión de expediente', 'Reunión con el cliente',
        'Diligencia / trámite', 'Declaración', 'Pericia', 'Otro',
    ];

    public static function puedeCambiarEtapa(): bool
    {
        $user = auth()->user();

        return $user && (! $user->hasRole(Rol::Procurador) || $user->puede('cambiar_etapa'));
    }

    /** Abogado/procuradora por defecto: el usuario actual (si es personal) o el primero del caso. */
    public static function personalPorDefecto(Caso $caso): ?int
    {
        return auth()->user()?->personal_id ?? $caso->personal()->value('personal.id');
    }

    /** @return array<int, string> Personal del caso primero, luego abogados y procuradoras. */
    public static function opcionesPersonal(Caso $caso): array
    {
        $nombre = fn (Personal $p) => [$p->id => $p->nombre_completo];

        return $caso->personal()->get()->mapWithKeys($nombre)
            ->union(Personal::query()->whereIn('profesion', ['Abogado', 'Procurador', 'Procuradora'])->orderBy('nombres')->get()->mapWithKeys($nombre))
            ->union(($propio = auth()->user()?->personal) ? [$propio->id => $propio->nombre_completo] : [])
            ->all();
    }

    public static function ultimo(Caso $caso): ?SeguimientoCaso
    {
        return $caso->seguimientos()->orderByDesc('fecha_seguimiento')->orderByDesc('id')->first();
    }

    /** Fecha, abogada, etapa e instancia (comunes a los dos formularios). */
    public static function camposCabecera(Caso $caso): Grid
    {
        $ultimo = self::ultimo($caso);
        $instancias = EtapasProceso::instancias($caso);

        return Grid::make(['default' => 1, 'md' => 2])->schema([
            DatePicker::make('fecha_seguimiento')
                ->label('Fecha')
                ->default(now()->toDateString())
                ->required(),
            Select::make('personal_id')
                ->label('Abogada / procuradora')
                ->options(fn () => self::opcionesPersonal($caso))
                ->default(fn () => self::personalPorDefecto($caso))
                ->searchable()
                ->required(),
            Select::make('etapa_proceso')
                ->label('Etapa del proceso')
                ->options(fn () => EtapasProceso::opciones($caso))
                ->default($ultimo?->etapa_proceso)
                ->searchable()
                ->disabled(! self::puedeCambiarEtapa())
                ->helperText(self::puedeCambiarEtapa() ? null : 'Para cambiar la etapa pide permiso a administración.'),
            Select::make('instancia')
                ->label('Instancia')
                ->options($instancias)
                ->default($ultimo?->instancia ?? array_key_first($instancias))
                ->searchable(),
        ]);
    }

    /**
     * Filas de actuados como tabla (N°, Actuado, Detalle, Fecha, Hora,
     * Alarma y, en el informe, Actuado concluido).
     */
    protected static function tablaActuados(string $nombre, bool $conConcluido): Repeater
    {
        $columnas = [
            TableColumn::make('Actuado')->markAsRequired()->width('11rem'),
            TableColumn::make('Detalle'),
            TableColumn::make('Fecha')->width('10rem'),
            TableColumn::make('Hora')->width('7rem'),
            TableColumn::make('Alarma')->width('5rem'),
        ];

        $campos = [
            Hidden::make('id'),
            Select::make('tipo')->options(array_combine(self::ACTUADOS, self::ACTUADOS))->required()->native(false),
            TextInput::make('detalle')->maxLength(500),
            DatePicker::make('fecha'),
            TimePicker::make('hora')->seconds(false),
            Toggle::make('alarma')->onColor('danger'),
        ];

        if ($conConcluido) {
            $columnas[] = TableColumn::make('Concluido')->width('6rem');
            $campos[] = Toggle::make('concluido')->onColor('success');
        }

        return Repeater::make($nombre)
            ->hiddenLabel()
            ->table($columnas)
            ->schema($campos)
            ->itemNumbers()
            ->reorderable(false)
            ->addActionLabel('Agregar otro actuado +')
            ->columnSpanFull();
    }

    /** Formulario "Registrar seguimiento diario". */
    public static function formularioSeguimiento(Caso $caso): array
    {
        $arrastrados = $caso->actuados()->pendientes()->count();

        return [
            self::camposCabecera($caso),
            Placeholder::make('actuados_titulo')
                ->hiddenLabel()
                ->content(new HtmlString('<strong>Actuados a realizar</strong>'
                    .($arrastrados ? ' <span style="color:rgb(107 114 128);font-size:.85em;">· además siguen pendientes '.$arrastrados.' de días anteriores (se ven en el informe)</span>' : ''))),
            self::tablaActuados('actuados', conConcluido: false)
                ->default([['tipo' => 'Seguimiento', 'detalle' => 'Diario', 'alarma' => false]])
                ->minItems(1),
        ];
    }

    /** Formulario "Registrar actuados realizados" (Informe). */
    public static function formularioInforme(Caso $caso): array
    {
        return [
            self::camposCabecera($caso),
            Placeholder::make('actuados_titulo')
                ->hiddenLabel()
                ->content(new HtmlString('<strong>Actuados a realizar</strong> <span style="color:rgb(107 114 128);font-size:.85em;">· marca "Concluido" en lo que se hizo; lo no concluido sigue pendiente mañana. Quitar una fila elimina ese actuado.</span>')),
            self::tablaActuados('actuados', conConcluido: true),
        ];
    }

    /**
     * Precarga del informe: fecha, abogada, etapa e instancia automáticas
     * (fillForm() reemplaza los default() de los campos) y los pendientes.
     */
    public static function datosInforme(Caso $caso): array
    {
        $ultimo = self::ultimo($caso);

        return [
            'fecha_seguimiento' => now()->toDateString(),
            'personal_id' => self::personalPorDefecto($caso),
            'etapa_proceso' => $ultimo?->etapa_proceso,
            'instancia' => $ultimo?->instancia ?? array_key_first(EtapasProceso::instancias($caso)),
            'actuados' => self::pendientesParaInforme($caso),
        ];
    }

    /** Pendientes para precargar el informe (con su id para actualizarlos). */
    public static function pendientesParaInforme(Caso $caso): array
    {
        return $caso->actuados()->pendientes()->orderByRaw('fecha is null')->orderBy('fecha')->orderBy('hora')->orderBy('id')->get()
            ->map(fn (ActuadoCaso $a) => [
                'id' => $a->id,
                'tipo' => $a->tipo,
                'detalle' => $a->detalle,
                'fecha' => $a->fecha?->toDateString(),
                'hora' => $a->hora ? substr($a->hora, 0, 5) : null,
                'alarma' => $a->alarma,
                'concluido' => false,
            ])->values()->all();
    }

    /** Datos comunes del registro (la etapa bloqueada conserva la anterior). */
    protected static function datosRegistro(Caso $caso, array $data, string $tipo): array
    {
        $personal = filled($data['personal_id'] ?? null) ? Personal::find($data['personal_id']) : null;

        return [
            'tipo_registro' => $tipo,
            'fecha_seguimiento' => $data['fecha_seguimiento'] ?? now(),
            'personal_id' => $personal?->id,
            'responsable' => $personal ? mb_substr($personal->nombre_completo, 0, 100) : null,
            'etapa_proceso' => array_key_exists('etapa_proceso', $data) ? $data['etapa_proceso'] : self::ultimo($caso)?->etapa_proceso,
            'instancia' => $data['instancia'] ?? null,
            'tipo_actuacion' => $tipo === 'informe' ? 'Informe' : 'Seguimiento',
        ];
    }

    protected static function datosActuado(array $fila): array
    {
        return [
            'tipo' => $fila['tipo'],
            'detalle' => filled($fila['detalle'] ?? null) ? trim($fila['detalle']) : null,
            'fecha' => $fila['fecha'] ?: null,
            'hora' => $fila['hora'] ?: null,
            'alarma' => (bool) ($fila['alarma'] ?? false),
        ];
    }

    /** Texto para la cronología y el portal del cliente (observaciones). */
    protected static function resumen(string $titulo, iterable $actuados): string
    {
        $lineas = collect($actuados)->map(fn ($a) => '• '.$a['tipo'].(filled($a['detalle'] ?? null) ? ': '.$a['detalle'] : ''));

        return $lineas->isEmpty() ? $titulo : $titulo."\n".$lineas->join("\n");
    }

    public static function guardarSeguimiento(Caso $caso, array $data): SeguimientoCaso
    {
        return DB::transaction(function () use ($caso, $data) {
            $filas = array_values(array_filter($data['actuados'] ?? [], fn ($f) => filled($f['tipo'] ?? null)));

            $registro = $caso->seguimientos()->create([
                ...self::datosRegistro($caso, $data, 'seguimiento'),
                'observaciones' => self::resumen('Actuados a realizar:', $filas),
            ]);

            foreach ($filas as $fila) {
                $registro->actuados()->create(['caso_id' => $caso->id, ...self::datosActuado($fila)]);
            }

            return $registro;
        });
    }

    public static function guardarInforme(Caso $caso, array $data): SeguimientoCaso
    {
        return DB::transaction(function () use ($caso, $data) {
            $filas = array_values(array_filter($data['actuados'] ?? [], fn ($f) => filled($f['tipo'] ?? null)));
            $pendientesAntes = $caso->actuados()->pendientes()->pluck('id');
            $idsEnInforme = collect($filas)->pluck('id')->filter()->map(fn ($id) => (int) $id);

            $informe = $caso->seguimientos()->create(self::datosRegistro($caso, $data, 'informe'));

            // Filas quitadas del informe: esos actuados se eliminan.
            $caso->actuados()->whereIn('id', $pendientesAntes->diff($idsEnInforme))->delete();

            $concluidos = [];

            foreach ($filas as $fila) {
                $concluido = (bool) ($fila['concluido'] ?? false);
                $valores = [
                    ...self::datosActuado($fila),
                    'concluido' => $concluido,
                    'concluido_at' => $concluido ? now() : null,
                    'informe_id' => $concluido ? $informe->id : null,
                ];

                $actuado = filled($fila['id'] ?? null) ? $caso->actuados()->find($fila['id']) : null;

                if ($actuado) {
                    $actuado->update($valores);
                } else {
                    // Agregado en el informe: queda ligado a este registro.
                    $caso->actuados()->create([...$valores, 'seguimiento_id' => $informe->id]);
                }

                if ($concluido) {
                    $concluidos[] = $fila;
                }
            }

            $informe->update(['observaciones' => self::resumen('Actuados realizados:', $concluidos)
                .(count($concluidos) < count($filas) ? "\nPendientes: ".(count($filas) - count($concluidos)) : '')]);

            return $informe;
        });
    }
}
