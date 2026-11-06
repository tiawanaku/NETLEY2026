<?php

namespace App\Filament\Admin\Support;

use App\Enums\EstadoCaso;
use App\Filament\Admin\Forms\Components\DelitoSelect;
use App\Filament\Admin\Support\CamposPago;
use App\Models\Caso;
use App\Models\Cliente;
use App\Models\DocumentoCliente;
use App\Models\Pago;
use App\Support\PersonalEspecialidades;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Los 3 pasos "Validación de datos" / "Proceso" / "Pago y plan de cuotas" y
 * la lógica de creación de cliente + caso + pago + plan de cuotas,
 * compartidos entre AscenderCasoAction (convertir una consulta en cliente
 * ejecutivo) y CreateCliente (dar de alta un cliente directamente con el
 * mismo flujo completo).
 */
class ClienteCasoWizard
{
    /** Opciones de "Apersonamiento"; "Otros" habilita "apersonamiento_otro". */
    public const APERSONAMIENTOS = ['Demandado', 'Demandante', 'Denunciante', 'Denunciado', 'Testigo', self::APERSONAMIENTO_OTROS];

    public const APERSONAMIENTO_OTROS = 'Otros';

    /**
     * @return array<int, Step>
     */
    public static function steps(): array
    {
        return [
            Step::make('Validación de datos')
                ->schema([
                    TextInput::make('nombres')
                        ->required()
                        ->regex('/^[\pL\s\'-]+$/u')
                        ->validationMessages(['regex' => 'Solo se permiten letras.'])
                        ->extraInputAttributes(self::atributosSoloLetras()),
                    TextInput::make('ap_paterno')
                        ->label('Apellido paterno')
                        ->required()
                        ->regex('/^[\pL\s\'-]+$/u')
                        ->validationMessages(['regex' => 'Solo se permiten letras.'])
                        ->extraInputAttributes(self::atributosSoloLetras()),
                    TextInput::make('ap_materno')
                        ->label('Apellido materno')
                        ->regex('/^[\pL\s\'-]+$/u')
                        ->validationMessages(['regex' => 'Solo se permiten letras.'])
                        ->extraInputAttributes(self::atributosSoloLetras()),
                    // Teléfono, WhatsApp y correo no se pueden repetir con
                    // OTRO cliente: se valida en este paso, antes de avanzar.
                    // El cliente con la misma CI no cuenta (crear() lo
                    // actualiza en vez de duplicarlo).
                    CamposTelefono::make('telefono', unicoEn: Cliente::class, entidadUnica: 'otro cliente', alcanceUnico: ReglasContacto::excluirMismaCi()),
                    CamposTelefono::make('whatsapp', unicoEn: Cliente::class, entidadUnica: 'otro cliente', alcanceUnico: ReglasContacto::excluirMismaCi()),
                    TextInput::make('correo')
                        ->email()
                        ->rules([ReglasContacto::correo(Cliente::class, 'otro cliente', ReglasContacto::excluirMismaCi())])
                        // Valida apenas se completa el campo (al perder el foco), no
                        // recién al guardar.
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (TextInput $component) => $component->getLivewire()->validateOnly($component->getStatePath()))
                        ->extraInputAttributes(['data-enter-nav' => 'true']),
                    CamposCi::make(),
                    DatePicker::make('fecha_nacimiento')->label('Fecha de nacimiento')->maxDate(now())->extraInputAttributes(['data-enter-nav' => 'true']),
                    ...CamposDomicilio::region(),
                    // Ver .netley-campo-ancho-alineado en admin-theme.css: sin
                    // esto, la etiqueta en línea queda tan ancha como el
                    // cuadro de arriba, desalineando el cuadro para rellenar.
                    TextInput::make('direccion')
                        ->label('Dirección')
                        ->columnSpanFull()
                        ->extraInputAttributes(['data-enter-nav' => 'true'])
                        ->extraFieldWrapperAttributes(['class' => 'netley-campo-ancho-alineado']),
                    ...CamposDomicilio::detalle(),
                    CamposDomicilio::mapa(),
                ])
                ->columns(2)
                ->inlineLabel(),

            Step::make('Proceso')
                ->schema(self::camposProceso())
                ->columns(2)
                ->inlineLabel(),

            Step::make('Pago y plan de cuotas')
                ->schema(self::camposPago())
                ->columns(1)
                ->inlineLabel(),

            Step::make('Adjuntar documentos')
                ->schema([
                    // Totalmente opcional: sin ->required() en "Archivo" a
                    // propósito, para que una fila sin archivo (agregada sin
                    // querer, o dejada a medias) nunca bloquee "Guardar" —
                    // guardarDocumentos() descarta sola cualquier fila sin
                    // archivo al guardar, así que no hace falta.
                    Repeater::make('documentos')
                        ->hiddenLabel()
                        ->extraAttributes(['class' => 'netley-fila-documento'])
                        ->schema([
                            // El archivo ya llega adjunto desde el clip (ver
                            // AdjuntarDocumentosClip): este campo solo sigue
                            // existiendo para que Filament lo guarde, pero
                            // no se muestra (ver .netley-documento-oculto) —
                            // el nombre del archivo se identifica con el
                            // Placeholder de al lado.
                            FileUpload::make('ruta')
                                ->hiddenLabel()
                                ->directory('documentos-clientes')
                                ->extraAttributes(['class' => 'netley-documento-oculto']),
                            Placeholder::make('nombreArchivo')
                                ->label('Archivo')
                                ->content(fn (Get $get) => AdjuntarDocumentosClip::nombreArchivo($get('ruta')) ?? '—'),
                            TextInput::make('descripcion')
                                ->label('Descripción')
                                ->maxLength(100),
                        ])
                        ->columns(2)
                        // Sin botón de agregar propio: lo reemplaza el campo
                        // "clip" de abajo (ver AdjuntarDocumentosClip), que
                        // sí puede abrir el explorador en un solo clic
                        // porque ya existe en el formulario desde el
                        // principio.
                        ->addable(false)
                        ->reorderable(false)
                        ->defaultItems(0)
                        ->columnSpanFull(),
                    AdjuntarDocumentosClip::make('documentos', 'documentos-clientes'),
                ])
                ->columns(1),
        ];
    }

    /**
     * Campos del paso "Proceso" (materia/delito, apersonamiento, descripción,
     * abogados asignados, fechas) — compartidos entre el wizard y el
     * formulario rápido de "Nuevo proceso" en la ficha del cliente
     * (CasosRelationManager), que crea un caso para un cliente ya existente.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function camposProceso(): array
    {
        return [
            // Materia legal y delito, por separado: primero se elige la
            // materia legal (área), que filtra las opciones de delito.
            ...DelitoSelect::make(delitoLabel: 'Tipo de proceso', conOtros: true),
            Select::make('apersonamiento')
                ->options(array_combine(self::APERSONAMIENTOS, self::APERSONAMIENTOS))
                ->native(false)
                ->live()
                ->extraAttributes(['data-enter-nav-field' => 'true', 'data-enter-nav-live' => 'true']),
            TextInput::make('apersonamiento_otro')
                ->label('Apersonamiento (especificar)')
                ->maxLength(60)
                ->required(fn (Get $get) => $get('apersonamiento') === self::APERSONAMIENTO_OTROS)
                ->visible(fn (Get $get) => $get('apersonamiento') === self::APERSONAMIENTO_OTROS)
                ->extraInputAttributes(['data-enter-nav' => 'true']),
            Textarea::make('descripcion')->label('Descripción')->columnSpanFull()->inlineLabel(false)->extraInputAttributes(['data-enter-nav' => 'true']),
            Select::make('personal')
                ->label('Abogado(s) asignado(s)')
                // Con materia "Otros" no hay especialidad que filtrar:
                // se listan todos los abogados.
                ->options(fn (Get $get) => PersonalEspecialidades::personalOptionsPlain(
                    DelitoSelect::especialidadDe($get),
                    (array) ($get('personal') ?? []),
                    'Abogado'
                ))
                ->multiple()
                ->required()
                ->searchable()
                ->helperText('Puede asignar uno o varios abogados.')
                ->extraAttributes(['data-enter-nav-field' => 'true']),
            DatePicker::make('fecha_inicio')->default(now())->required()->live()->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('duracion_meses')
                ->label('Duración del proceso (meses)')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->required()
                ->live(onBlur: true)
                // El plan de pagos (paso 3) arranca con una cuota por mes.
                ->afterStateUpdated(fn ($set, $state) => $set('numero_cuotas', filled($state) ? max((int) $state, 1) : null))
                ->extraInputAttributes(['data-enter-nav' => 'true']),
            Placeholder::make('fecha_fin_preview')
                ->label('Fecha estimada de fin (aprox.)')
                ->content(function (Get $get): string {
                    $fin = self::fechaFinEstimada($get('duracion_meses'));

                    return $fin ? $fin->translatedFormat('d/m/Y').' (desde hoy)' : '—';
                }),
        ];
    }

    /**
     * Campos del paso "Pago y plan de cuotas" — ver camposProceso().
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function camposPago(): array
    {
        return [
            TextInput::make('iguala')
                ->label('Iguala profesional')
                ->numeric()->minValue(0)->prefix('Bs.')->required()->live(onBlur: true)
                ->extraInputAttributes(self::atributosEnterPago()),

            Toggle::make('patrocinio_hih')
                ->label('Patrocinio Hand in Hand (HIH)')
                ->default(false)
                ->live(),
            // El monto se carga en bolivianos (como el resto del paso) y el
            // porcentaje que representa de la iguala profesional se calcula
            // solo, al lado — antes era al revés (se cargaba el % a mano).
            Group::make([
                TextInput::make('monto_patrocinio_hih')
                    ->label('Hand in Hand (HIH)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('Bs.')
                    ->maxValue(fn (Get $get) => (float) ($get('iguala') ?? 0))
                    ->required(fn (Get $get) => (bool) $get('patrocinio_hih'))
                    ->live(onBlur: true)
                    ->extraInputAttributes(self::atributosEnterPago()),
                Placeholder::make('porcentaje_patrocinio_preview')
                    ->label('Porcentaje de la iguala')
                    ->content(function (Get $get): string {
                        $iguala = (float) ($get('iguala') ?? 0);
                        $monto = (float) ($get('monto_patrocinio_hih') ?? 0);

                        return $iguala > 0
                            ? number_format(min($monto / $iguala * 100, 100), 2).'%'
                            : '—';
                    }),
            ])
                ->columns(2)
                ->visible(fn (Get $get) => (bool) $get('patrocinio_hih')),

            // Iguala Netley = iguala profesional menos el descuento que
            // cubre HIH: es el monto real que el cliente le debe a
            // Netley, y la base de todo lo que sigue (anticipo, saldo,
            // cuotas) — por eso también es lo que se guarda como
            // Caso.iguala/saldo, no la iguala profesional completa.
            Placeholder::make('iguala_netley_preview')
                ->label('Iguala Netley')
                ->content(fn (Get $get) => 'Bs. '.number_format(self::plan(self::datosPlan($get))['monto_cliente'], 2)),

            // Primera cuota del plan de pagos (ver plan()): empieza vacío
            // (no en 0) y, si se carga, nunca puede ser 0 — un anticipo de
            // 0 no es un anticipo.
            TextInput::make('anticipo')
                ->label('Anticipo')
                ->numeric()
                ->prefix('Bs.')
                ->minValue(0.01)
                ->maxValue(fn (Get $get) => self::plan(self::datosPlan($get))['monto_cliente'])
                ->validationMessages(['min' => 'El anticipo no puede ser 0; déjalo vacío si no hay anticipo.'])
                ->live(onBlur: true)
                ->extraInputAttributes(self::atributosEnterPago()),

            // Saldo Netley = Iguala Netley menos el anticipo; es lo que
            // se reparte en las cuotas de abajo (plan de pagos).
            Placeholder::make('saldo_netley_preview')
                ->label('Saldo Netley')
                ->content(fn (Get $get) => 'Bs. '.number_format(self::plan(self::datosPlan($get))['saldo_cliente'], 2)),

            // Comisión por un pago extra ocasional (ej. referido): dos
            // campos sueltos, sin relación entre sí ni con el resto —
            // solo se guardan tal cual se cargan.
            TextInput::make('comision_porcentaje')
                ->label('Comisión (%)')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->extraInputAttributes(self::atributosEnterPago()),
            TextInput::make('comision_monto')
                ->label('Comisión (Bs.)')
                ->numeric()
                ->minValue(0)
                ->prefix('Bs.')
                ->extraInputAttributes(self::atributosEnterPago()),

            TextInput::make('numero_cuotas')
                ->label('N° de cuotas mensuales')
                ->helperText(fn (Get $get) => filled($get('duracion_meses'))
                    ? 'Según la duración del proceso: '.(int) $get('duracion_meses').' mes(es).'
                    : null)
                ->numeric()
                ->integer()
                ->minValue(1)
                // No puede haber más cuotas que meses dure el proceso (paso "Proceso").
                ->maxValue(fn (Get $get) => filled($get('duracion_meses')) ? (int) $get('duracion_meses') : null)
                ->validationMessages(['max' => 'No puede exceder la duración del proceso (:max mes(es)).'])
                ->required(fn (Get $get) => self::plan(self::datosPlan($get))['saldo_cliente'] > 0)
                ->visible(fn (Get $get) => self::plan(self::datosPlan($get))['saldo_cliente'] > 0)
                ->live(onBlur: true)
                ->rule(fn (Get $get) => self::reglaUltimoPagoAntesDeFin($get))
                ->extraInputAttributes(self::atributosEnterPago()),
            DatePicker::make('fecha_primera_cuota')
                ->label('Fecha de la primera cuota')
                ->default(now()->addMonth())
                ->required(fn (Get $get) => self::plan(self::datosPlan($get))['saldo_cliente'] > 0)
                ->visible(fn (Get $get) => self::plan(self::datosPlan($get))['saldo_cliente'] > 0)
                ->live()
                ->rule(fn (Get $get) => self::reglaUltimoPagoAntesDeFin($get))
                ->extraInputAttributes(self::atributosEnterPago()),

            // Datos del cobro de hoy (si hay anticipo).
            Group::make(CamposPago::components(conTipo: false))
                ->visible(fn (Get $get) => self::plan(self::datosPlan($get))['pago_hoy'] > 0)
                ->columns(2)
                ->columnSpanFull(),

            Placeholder::make('plan_preview')
                ->label('Resumen')
                ->content(fn (Get $get) => self::resumenPlanHtml(self::plan(self::datosPlan($get))))
                ->columnSpanFull()
                ->inlineLabel(false),
        ];
    }

    /**
     * Crea (o actualiza, si ya existe un cliente con esa CI) el cliente, su
     * caso, el pago del anticipo (si hay) y el plan de cuotas — incluida la
     * cuota 0 = anticipo ya pagado.
     *
     * @param  array<string, mixed>  $data
     * @return array{cliente: Cliente, caso: Caso}
     */
    public static function crear(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            // Si ya existe un cliente con esa CI (consulta recurrente), se
            // actualizan sus datos en vez de crear un duplicado.
            $cliente = Cliente::updateOrCreate(
                ['ci' => $data['ci']],
                [
                    'nombres' => $data['nombres'],
                    'ap_paterno' => $data['ap_paterno'],
                    'ap_materno' => $data['ap_materno'] ?? null,
                    'telefono' => $data['telefono'] ?? null,
                    'whatsapp' => $data['whatsapp'] ?? null,
                    'correo' => $data['correo'] ?? null,
                    'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
                    'direccion' => $data['direccion'] ?? null,
                    'pais' => $data['pais'] ?? null,
                    'provincia' => $data['provincia'] ?? null,
                    'ciudad' => $data['ciudad'] ?? null,
                    'zona' => $data['zona'] ?? null,
                    'calles' => $data['calles'] ?? null,
                    'numero_domicilio' => $data['numero_domicilio'] ?? null,
                    'indicaciones_domicilio' => $data['indicaciones_domicilio'] ?? null,
                    'ubicacion' => CamposDomicilio::ubicacionValida($data['ubicacion'] ?? null),
                    'extension' => $data['extension'] ?? null,
                ]
            );

            $caso = self::crearCaso($cliente, $data);

            self::guardarDocumentos($cliente, $caso, $data['documentos'] ?? []);

            return ['cliente' => $cliente, 'caso' => $caso];
        });
    }

    /**
     * Documentos subidos en el paso "Adjuntar documentos" del wizard: quedan
     * en la misma tabla que el resto de los documentos del cliente (ver
     * App\Filament\Admin\Resources\Clientes\RelationManagers\DocumentosRelationManager),
     * ligados también a este caso recién creado. Subir documentos acá es
     * opcional; si no se sube ninguno, el caso queda marcado con
     * "documentos_pendientes" (ver CasosTable y Clientes\RelationManagers\CasosRelationManager)
     * hasta que se suba alguno más adelante desde la ficha del cliente —
     * eso ya apaga la marca sola (ver DocumentosRelationManager).
     *
     * @param  array<int, array{ruta?: string, descripcion?: ?string}>  $documentos
     */
    protected static function guardarDocumentos(Cliente $cliente, Caso $caso, array $documentos): void
    {
        $huboDocumento = false;

        foreach ($documentos as $documento) {
            if (blank($documento['ruta'] ?? null)) {
                continue;
            }

            DocumentoCliente::create([
                'cliente_id' => $cliente->id,
                'caso_id' => $caso->id,
                'ruta' => $documento['ruta'],
                'descripcion' => $documento['descripcion'] ?? null,
                'fecha_origen' => now(),
            ]);

            $huboDocumento = true;
        }

        $caso->update(['documentos_pendientes' => ! $huboDocumento]);
    }

    /**
     * Crea un caso (+ pago del anticipo y plan de cuotas, si corresponde)
     * para un cliente ya existente, a partir de los datos de los pasos
     * "Proceso" y "Pago y plan de cuotas" (camposProceso()/camposPago()).
     * Lo usa crear() (cliente nuevo) y CasosRelationManager (cliente ya
     * existente, acción "Nuevo proceso" en su ficha).
     *
     * @param  array<string, mixed>  $data
     */
    public static function crearCaso(Cliente $cliente, array $data): Caso
    {
        return DB::transaction(function () use ($cliente, $data): Caso {
            $plan = self::plan($data);
            // Caso.iguala/saldo son la Iguala Netley (lo que el cliente
            // realmente le debe a Netley), no la iguala profesional completa
            // — la iguala profesional siempre se puede reconstruir sumando
            // monto_patrocinio.
            $iguala = $plan['monto_cliente'];
            // Materia legal = área del delito elegido en "Delitos" (o la
            // escrita a mano con "Otro"), ver DelitoSelect::datosParaGuardar().
            $delito = DelitoSelect::datosParaGuardar($data);

            $duracionMeses = filled($data['duracion_meses'] ?? null) ? (int) $data['duracion_meses'] : null;
            $fechaFin = self::fechaFinEstimada($duracionMeses)?->toDateString();

            // saldo/pagado arrancan en el monto total; el pago del anticipo (si existe)
            // los ajusta automáticamente vía PagoObserver al crearse más abajo.
            $caso = Caso::create([
                'cliente_id' => $cliente->id,
                ...$delito,
                'descripcion' => $data['descripcion'] ?? null,
                'apersonamiento' => ($data['apersonamiento'] ?? null) === self::APERSONAMIENTO_OTROS
                    ? ($data['apersonamiento_otro'] ?? null)
                    : ($data['apersonamiento'] ?? null),
                'denunciado' => filled($data['denunciado'] ?? null) ? $data['denunciado'] : null,
                'iguala' => $iguala,
                'saldo' => $iguala,
                'pagado' => 0,
                'patrocinio_hih' => $plan['patrocinio'],
                'porcentaje_patrocinio' => $plan['patrocinio'] ? $plan['porcentaje'] : null,
                'monto_patrocinio' => $plan['monto_patrocinio'],
                'comision_porcentaje' => filled($data['comision_porcentaje'] ?? null) ? (float) $data['comision_porcentaje'] : null,
                'comision_monto' => filled($data['comision_monto'] ?? null) ? (float) $data['comision_monto'] : null,
                'fecha_inicio' => $data['fecha_inicio'],
                'duracion_meses' => $duracionMeses,
                'fecha_fin' => $fechaFin,
                'estado' => EstadoCaso::ActivoPendiente,
            ]);

            $cliente->increment('nro_casos');

            if (filled($data['personal'] ?? null)) {
                $caso->personal()->sync($data['personal']);
            }

            CamposDenuncia::sincronizar($caso, $data['denuncias'] ?? []);

            // Cobro de hoy: el anticipo (puede ser el total de la Iguala
            // Netley si así se cargó) — es la cuota 1 del plan (ver plan()),
            // no una cuota 0 aparte.
            if ($plan['pago_hoy'] > 0) {
                $siguienteRecibo = ((int) Pago::max('nro_recibo')) + 1;

                Pago::create([
                    'caso_id' => $caso->id,
                    'cliente_id' => $cliente->id,
                    'monto' => $plan['pago_hoy'],
                    ...CamposPago::datos($data, tipoFijo: 'anticipo'),
                    'fecha_pago' => now()->toDateString(),
                    'nro_cuota' => 1,
                    'nro_recibo' => $siguienteRecibo,
                    'registrado_por' => auth()->user()?->username,
                ]);
            }

            foreach ($plan['cuotas'] as $cuota) {
                $caso->planesPago()->create([
                    'numero' => $cuota['numero'],
                    'fecha' => $cuota['fecha']->toDateString(),
                    'monto' => $cuota['monto'],
                    'nuevo_saldo' => $cuota['nuevo_saldo'],
                    'estado' => $cuota['pagada_hoy'] ? 'pagado' : 'pendiente',
                    'creado_por' => auth()->user()?->username,
                ]);
            }

            return $caso;
        });
    }

    /**
     * Reparto y plan de pagos a partir de los datos del paso 3. Lo usan la
     * vista previa y crear(), así lo que se ve es exactamente lo que se
     * guarda.
     *
     * - Iguala profesional, con un descuento opcional de Hand in Hand (HIH)
     *   cargado en bolivianos (el porcentaje que representa se calcula
     *   solo), da la Iguala Netley (lo que el cliente le debe a Netley).
     * - El anticipo es la cuota 1 del plan de pagos (no una "cuota 0"
     *   aparte): se resta de la Iguala Netley y da el Saldo, que se reparte
     *   en las cuotas mensuales siguientes (por defecto, una por mes de
     *   duración del proceso), numeradas a partir de la 2 si hubo anticipo,
     *   o desde la 1 si no lo hubo; la última cuota absorbe el redondeo
     *   para que la suma cuadre. Sin anticipo ni cuotas, el saldo queda
     *   pendiente sin más.
     *
     * @param  array<string, mixed>  $datos
     * @return array{iguala: float, patrocinio: bool, porcentaje: float, monto_patrocinio: float, monto_cliente: float, pago_hoy: float, saldo_cliente: float, cuotas: array<int, array{numero: int, fecha: Carbon, monto: float, nuevo_saldo: float, pagada_hoy: bool}>}
     */
    public static function plan(array $datos): array
    {
        $iguala = round(max((float) ($datos['iguala'] ?? 0), 0), 2);
        $patrocinio = (bool) ($datos['patrocinio_hih'] ?? false);
        $montoPatrocinio = $patrocinio
            ? round(min(max((float) ($datos['monto_patrocinio_hih'] ?? 0), 0), $iguala), 2)
            : 0.0;
        $porcentaje = ($patrocinio && $iguala > 0) ? round(min($montoPatrocinio / $iguala * 100, 100), 2) : 0.0;

        $montoCliente = round($iguala - $montoPatrocinio, 2);

        $pagoHoy = round(min(max((float) ($datos['anticipo'] ?? 0), 0), $montoCliente), 2);

        $saldoCliente = round($montoCliente - $pagoHoy, 2);
        $numeroCuotas = max((int) ($datos['numero_cuotas'] ?? 0), 0);

        $cuotas = [];
        $siguienteNumero = 1;

        if ($pagoHoy > 0) {
            $cuotas[] = [
                'numero' => $siguienteNumero,
                'fecha' => now()->startOfDay(),
                'monto' => $pagoHoy,
                'nuevo_saldo' => $saldoCliente,
                'pagada_hoy' => true,
            ];
            $siguienteNumero++;
        }

        if ($numeroCuotas > 0 && $saldoCliente > 0) {
            $montoCuota = round($saldoCliente / $numeroCuotas, 2);
            $primera = filled($datos['fecha_primera_cuota'] ?? null)
                ? Carbon::parse($datos['fecha_primera_cuota'])
                : now()->addMonth();
            $restante = $saldoCliente;

            for ($i = 0; $i < $numeroCuotas; $i++) {
                $monto = $i === $numeroCuotas - 1 ? $restante : $montoCuota;
                $restante = round($restante - $monto, 2);

                $cuotas[] = [
                    'numero' => $siguienteNumero + $i,
                    'fecha' => $primera->copy()->addMonthsNoOverflow($i),
                    'monto' => $monto,
                    'nuevo_saldo' => max($restante, 0),
                    'pagada_hoy' => false,
                ];
            }
        }

        return [
            'iguala' => $iguala,
            'patrocinio' => $patrocinio,
            'porcentaje' => $porcentaje,
            'monto_patrocinio' => $montoPatrocinio,
            'monto_cliente' => $montoCliente,
            'pago_hoy' => $pagoHoy,
            'saldo_cliente' => $saldoCliente,
            'cuotas' => $cuotas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function datosPlan(Get $get): array
    {
        return collect(['iguala', 'patrocinio_hih', 'monto_patrocinio_hih', 'anticipo', 'numero_cuotas', 'fecha_primera_cuota'])
            ->mapWithKeys(fn (string $campo) => [$campo => $get($campo)])
            ->all();
    }

    /**
     * El último pago del plan (la última cuota mensual, o el anticipo si no
     * hay cuotas) tiene que caer antes de la fecha estimada de fin del
     * proceso (paso "Proceso", ver fechaFinEstimada()) — si no, el cliente
     * terminaría de pagar después de que el caso debería estar cerrado.
     */
    protected static function reglaUltimoPagoAntesDeFin(Get $get): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
            $fechaFin = self::fechaFinEstimada($get('duracion_meses'));

            if (! $fechaFin) {
                return;
            }

            $ultimaCuota = collect(self::plan(self::datosPlan($get))['cuotas'])->last();

            if (! $ultimaCuota) {
                return;
            }

            if ($ultimaCuota['fecha']->greaterThanOrEqualTo($fechaFin)) {
                $fail('El último pago ('.$ultimaCuota['fecha']->format('d/m/Y').') debe ser antes de la fecha estimada de fin del proceso ('.$fechaFin->format('d/m/Y').').');
            }
        };
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    protected static function resumenPlanHtml(array $plan): HtmlString
    {
        $bs = fn (float $monto): string => 'Bs. '.number_format($monto, 2);

        // Todo en tabla (resumen y cuotas) para que los montos queden
        // alineados a la derecha en vez de sueltos en una lista.
        $filaResumen = fn (string $etiqueta, string $valor, bool $fuerte = false): string => '<tr>'
            .'<td style="padding:4px 8px;color:#666;">'.e($etiqueta).'</td>'
            .'<td style="padding:4px 8px;text-align:right;'.($fuerte ? 'font-weight:bold;' : '').'">'.e($valor).'</td></tr>';

        $resumen = '<table style="border-collapse:collapse;font-size:.875rem;">';
        $resumen .= $filaResumen('Iguala profesional', $bs($plan['iguala']));

        if ($plan['patrocinio']) {
            $porcentaje = rtrim(rtrim(number_format($plan['porcentaje'], 2), '0'), '.');
            $resumen .= $filaResumen('Hand in Hand ('.$porcentaje.'%)', $bs($plan['monto_patrocinio']));
        }

        $resumen .= $filaResumen('Iguala Netley', $bs($plan['monto_cliente']), fuerte: true);
        $resumen .= $filaResumen('Anticipo', $bs($plan['pago_hoy']));
        $resumen .= $filaResumen('Saldo', $bs($plan['saldo_cliente']), fuerte: true);
        $resumen .= '</table>';

        $tablaCuotas = '';

        if ($plan['cuotas'] !== []) {
            $filaCuota = fn (string $a, string $b, string $c, string $d): string => '<tr>'
                .'<td style="padding:4px 8px;">'.e($a).'</td>'
                .'<td style="padding:4px 8px;">'.e($b).'</td>'
                .'<td style="padding:4px 8px;text-align:right;">'.e($c).'</td>'
                .'<td style="padding:4px 8px;text-align:right;">'.e($d).'</td></tr>';

            $tablaCuotas = '<table style="margin-top:.75rem;border-collapse:collapse;font-size:.875rem;">'
                .'<thead><tr style="text-align:left;border-bottom:1px solid rgb(128 128 128 / .3);">'
                .'<th style="padding:4px 8px;">Cuota</th><th style="padding:4px 8px;">Fecha</th>'
                .'<th style="padding:4px 8px;text-align:right;">Monto</th><th style="padding:4px 8px;text-align:right;">Saldo</th></tr></thead><tbody>';

            foreach ($plan['cuotas'] as $cuota) {
                // La cuota 1 es el anticipo pagado hoy (ver plan()): se
                // marca acá para distinguirla, no es una fila aparte.
                $numero = $cuota['numero'].($cuota['pagada_hoy'] ? ' (anticipo, hoy)' : '');
                $tablaCuotas .= $filaCuota($numero, $cuota['fecha']->format('d/m/Y'), $bs($cuota['monto']), $bs($cuota['nuevo_saldo']));
            }

            $tablaCuotas .= '</tbody></table>';
        }

        return new HtmlString($resumen.$tablaCuotas);
    }

    /**
     * Estimado aproximado del fin del proceso: fecha actual + duración en
     * meses (no la fecha de inicio, que puede ser anterior a hoy).
     */
    public static function fechaFinEstimada(mixed $meses): ?Carbon
    {
        return filled($meses) && (int) $meses > 0
            ? now()->startOfDay()->addMonthsNoOverflow((int) $meses)
            : null;
    }

    /**
     * @return array<string, string>
     */
    protected static function atributosSoloLetras(): array
    {
        return [
            'data-enter-nav' => 'true',
            'oninput' => 'this.value=this.value.replace(/[0-9]/g,\'\')',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function atributosSoloNumeros(): array
    {
        return [
            'data-enter-nav' => 'true',
            'inputmode' => 'numeric',
            'oninput' => 'this.value=this.value.replace(/[^0-9]/g,\'\')',
        ];
    }

    /**
     * Campos del paso "Pago y plan de cuotas". Ya no es el último paso del
     * asistente (ver steps(), ahora sigue "Adjuntar documentos"), así que al
     * llegar al final de la cadena con Enter corresponde pasar al siguiente
     * paso con "Siguiente" — no hace falta bloquear el autoavance como
     * cuando este era el paso final (ver resources/js/filament/enter-navigation.js:
     * "Siguiente" ya está marcado con data-enter-submit en el propio Wizard,
     * igual que el botón real de guardar).
     *
     * @return array<string, string>
     */
    protected static function atributosEnterPago(): array
    {
        return [
            'data-enter-nav' => 'true',
        ];
    }
}
