<?php

namespace App\Filament\Admin\Support;

use App\Enums\EstadoCaso;
use App\Filament\Admin\Forms\Components\DelitoSelect;
use App\Filament\Admin\Support\CamposPago;
use App\Models\Caso;
use App\Models\Cliente;
use App\Models\Pago;
use App\Support\PersonalEspecialidades;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
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
    /** Valor de "extension" que habilita el campo manual "extension_texto". */
    public const EXTENSION_OTRO = 'OTRO';

    /** Opciones de "Apersonamiento"; "Otros" habilita "apersonamiento_otro". */
    public const APERSONAMIENTOS = ['Demandado', 'Demandante', 'Testigo', self::APERSONAMIENTO_OTROS];

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
                    TextInput::make('telefono')
                        ->label('Teléfono')
                        ->tel()
                        ->regex('/^[0-9+\-\s]+$/')
                        ->validationMessages(['regex' => 'Solo se permiten números.'])
                        ->extraInputAttributes(self::atributosSoloNumeros()),
                    TextInput::make('whatsapp')
                        ->tel()
                        ->regex('/^[0-9+\-\s]+$/')
                        ->validationMessages(['regex' => 'Solo se permiten números.'])
                        ->extraInputAttributes(self::atributosSoloNumeros()),
                    TextInput::make('correo')->email()->extraInputAttributes(['data-enter-nav' => 'true']),
                    TextInput::make('ci')
                        ->label('Carnet de identidad')
                        ->required()
                        ->regex('/^[0-9]+$/')
                        ->validationMessages(['regex' => 'Solo se permiten números.'])
                        ->extraInputAttributes(self::atributosSoloNumeros()),
                    Select::make('extension')
                        ->label('Expedido en')
                        ->options([
                            'LP' => 'La Paz',
                            'CB' => 'Cochabamba',
                            'SC' => 'Santa Cruz',
                            'OR' => 'Oruro',
                            'PT' => 'Potosí',
                            'TJ' => 'Tarija',
                            'CH' => 'Chuquisaca',
                            'BE' => 'Beni',
                            'PD' => 'Pando',
                            'QR' => 'QR',
                            self::EXTENSION_OTRO => 'Otro',
                        ])
                        ->native(false)
                        ->live()
                        ->extraAttributes(['data-enter-nav-field' => 'true', 'data-enter-nav-live' => 'true']),
                    TextInput::make('extension_texto')
                        ->label('Expedido en (manual)')
                        ->maxLength(20)
                        ->visible(fn (Get $get) => $get('extension') === self::EXTENSION_OTRO)
                        ->extraInputAttributes(['data-enter-nav' => 'true']),
                    DatePicker::make('fecha_nacimiento')->label('Fecha de nacimiento')->maxDate(now())->extraInputAttributes(['data-enter-nav' => 'true']),
                    ...CamposDomicilio::region(),
                    TextInput::make('direccion')->label('Dirección')->columnSpanFull()->extraInputAttributes(['data-enter-nav' => 'true']),
                    ...CamposDomicilio::detalle(),
                    CamposDomicilio::mapa(),
                ])
                ->columns(2)
                ->inlineLabel(),

            Step::make('Proceso')
                ->schema([
                    ...DelitoSelect::make(areaLabel: 'Materia legal', conOtros: true),
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
                            $get('especialidad') === DelitoSelect::OTROS ? null : $get('especialidad'),
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
                ])
                ->columns(2)
                ->inlineLabel(),

            Step::make('Pago y plan de cuotas')
                ->schema([
                    TextInput::make('iguala')
                        ->label('Iguala profesional')
                        ->numeric()->minValue(0)->prefix('Bs.')->required()->live(onBlur: true)
                        ->extraInputAttributes(['data-enter-nav' => 'true']),

                    Toggle::make('patrocinio_hih')
                        ->label('Patrocinio Hand in Hand (HIH)')
                        ->default(false)
                        ->live(),
                    TextInput::make('porcentaje_patrocinio')
                        ->label('Porcentaje de patrocinio')
                        ->helperText('Parte de la iguala profesional que cubre Hand in Hand (ej. 40, 30).')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->suffix('%')
                        ->datalist(['10', '20', '30', '40', '50', '60', '70', '80', '90'])
                        ->required(fn (Get $get) => (bool) $get('patrocinio_hih'))
                        ->visible(fn (Get $get) => (bool) $get('patrocinio_hih'))
                        ->live(onBlur: true)
                        ->extraInputAttributes(['data-enter-nav' => 'true']),

                    // Iguala Netley = iguala profesional menos el descuento que
                    // cubre HIH: es el monto real que el cliente le debe a
                    // Netley, y la base de todo lo que sigue (anticipo, saldo,
                    // cuotas) — por eso también es lo que se guarda como
                    // Caso.iguala/saldo, no la iguala profesional completa.
                    Placeholder::make('iguala_netley_preview')
                        ->label('Iguala Netley')
                        ->content(fn (Get $get) => 'Bs. '.number_format(self::plan(self::datosPlan($get))['monto_cliente'], 2)),

                    TextInput::make('anticipo')
                        ->label('Anticipo')
                        ->numeric()
                        ->prefix('Bs.')
                        ->default(0)
                        ->minValue(0)
                        ->maxValue(fn (Get $get) => self::plan(self::datosPlan($get))['monto_cliente'])
                        ->live(onBlur: true)
                        ->extraInputAttributes(['data-enter-nav' => 'true']),

                    // Saldo = Iguala Netley menos el anticipo; es lo que se
                    // reparte en las cuotas de abajo.
                    Placeholder::make('saldo_netley_preview')
                        ->label('Saldo')
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
                        ->extraInputAttributes(['data-enter-nav' => 'true']),
                    TextInput::make('comision_monto')
                        ->label('Comisión (Bs.)')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Bs.')
                        ->extraInputAttributes(['data-enter-nav' => 'true']),

                    TextInput::make('numero_cuotas')
                        ->label('N° de cuotas mensuales')
                        ->helperText(fn (Get $get) => filled($get('duracion_meses'))
                            ? 'Según la duración del proceso: '.(int) $get('duracion_meses').' mes(es).'
                            : null)
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required(fn (Get $get) => self::plan(self::datosPlan($get))['saldo_cliente'] > 0)
                        ->visible(fn (Get $get) => self::plan(self::datosPlan($get))['saldo_cliente'] > 0)
                        ->live(onBlur: true)
                        ->extraInputAttributes(['data-enter-nav' => 'true']),
                    DatePicker::make('fecha_primera_cuota')
                        ->label('Fecha de la primera cuota')
                        ->default(now()->addMonth())
                        ->required(fn (Get $get) => self::plan(self::datosPlan($get))['saldo_cliente'] > 0)
                        ->visible(fn (Get $get) => self::plan(self::datosPlan($get))['saldo_cliente'] > 0)
                        ->live()
                        ->extraInputAttributes(['data-enter-nav' => 'true']),

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
                ])
                ->columns(2)
                ->inlineLabel(),
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
            $plan = self::plan($data);
            // Caso.iguala/saldo son la Iguala Netley (lo que el cliente
            // realmente le debe a Netley), no la iguala profesional completa
            // — la iguala profesional siempre se puede reconstruir sumando
            // monto_patrocinio.
            $iguala = $plan['monto_cliente'];
            $esOtraMateria = ($data['especialidad'] ?? null) === DelitoSelect::OTROS;
            $esOtroDelito = ($data['delito_id'] ?? null) === DelitoSelect::OTROS;
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
                    'extension' => ($data['extension'] ?? null) === self::EXTENSION_OTRO
                        ? ($data['extension_texto'] ?? null)
                        : ($data['extension'] ?? null),
                ]
            );

            $duracionMeses = filled($data['duracion_meses'] ?? null) ? (int) $data['duracion_meses'] : null;
            $fechaFin = self::fechaFinEstimada($duracionMeses)?->toDateString();

            // saldo/pagado arrancan en el monto total; el pago del anticipo (si existe)
            // los ajusta automáticamente vía PagoObserver al crearse más abajo.
            $caso = Caso::create([
                'cliente_id' => $cliente->id,
                'especialidad' => $esOtraMateria ? null : $data['especialidad'],
                'materia_texto' => $esOtraMateria ? ($data['materia_texto'] ?? null) : null,
                'delito_id' => $esOtraMateria || $esOtroDelito ? null : ($data['delito_id'] ?? null),
                'delito_texto' => $esOtraMateria || $esOtroDelito ? ($data['delito_texto'] ?? null) : null,
                'descripcion' => $data['descripcion'] ?? null,
                'apersonamiento' => ($data['apersonamiento'] ?? null) === self::APERSONAMIENTO_OTROS
                    ? ($data['apersonamiento_otro'] ?? null)
                    : ($data['apersonamiento'] ?? null),
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

            // Cobro de hoy: el anticipo (puede ser el total de la Iguala
            // Netley si así se cargó).
            if ($plan['pago_hoy'] > 0) {
                $siguienteRecibo = ((int) Pago::max('nro_recibo')) + 1;

                Pago::create([
                    'caso_id' => $caso->id,
                    'cliente_id' => $cliente->id,
                    'monto' => $plan['pago_hoy'],
                    ...CamposPago::datos($data, tipoFijo: 'anticipo'),
                    'fecha_pago' => now()->toDateString(),
                    'nro_cuota' => 0,
                    'nro_recibo' => $siguienteRecibo,
                    'registrado_por' => auth()->user()?->username,
                ]);

                // Cuota 0 del plan = lo cobrado hoy, ya pagado, para que
                // aparezca junto con el resto de las cuotas.
                $caso->planesPago()->create([
                    'numero' => 0,
                    'fecha' => now()->toDateString(),
                    'monto' => $plan['pago_hoy'],
                    'nuevo_saldo' => $plan['saldo_cliente'],
                    'estado' => 'pagado',
                    'creado_por' => auth()->user()?->username,
                ]);
            }

            foreach ($plan['cuotas'] as $cuota) {
                $caso->planesPago()->create([
                    'numero' => $cuota['numero'],
                    'fecha' => $cuota['fecha']->toDateString(),
                    'monto' => $cuota['monto'],
                    'nuevo_saldo' => $cuota['nuevo_saldo'],
                    'estado' => 'pendiente',
                    'creado_por' => auth()->user()?->username,
                ]);
            }

            return ['cliente' => $cliente, 'caso' => $caso];
        });
    }

    /**
     * Reparto y plan de pagos a partir de los datos del paso 3. Lo usan la
     * vista previa y crear(), así lo que se ve es exactamente lo que se
     * guarda.
     *
     * - Iguala profesional, con un descuento opcional de Hand in Hand (HIH)
     *   según su porcentaje de patrocinio, da la Iguala Netley (lo que el
     *   cliente le debe a Netley).
     * - El anticipo se resta de la Iguala Netley y da el Saldo, que se
     *   reparte en N cuotas mensuales (por defecto, una por mes de duración
     *   del proceso); la última cuota absorbe el redondeo para que la suma
     *   cuadre. Sin anticipo ni cuotas, el saldo queda pendiente sin más.
     *
     * @param  array<string, mixed>  $datos
     * @return array{iguala: float, patrocinio: bool, porcentaje: float, monto_patrocinio: float, monto_cliente: float, pago_hoy: float, saldo_cliente: float, cuotas: array<int, array{numero: int, fecha: Carbon, monto: float, nuevo_saldo: float}>}
     */
    public static function plan(array $datos): array
    {
        $iguala = round(max((float) ($datos['iguala'] ?? 0), 0), 2);
        $patrocinio = (bool) ($datos['patrocinio_hih'] ?? false);
        $porcentaje = $patrocinio ? min(max((float) ($datos['porcentaje_patrocinio'] ?? 0), 0), 100) : 0.0;

        $montoPatrocinio = round($iguala * $porcentaje / 100, 2);
        $montoCliente = round($iguala - $montoPatrocinio, 2);

        $pagoHoy = round(min(max((float) ($datos['anticipo'] ?? 0), 0), $montoCliente), 2);

        $saldoCliente = round($montoCliente - $pagoHoy, 2);
        $numeroCuotas = max((int) ($datos['numero_cuotas'] ?? 0), 0);

        $cuotas = [];

        if ($numeroCuotas > 0 && $saldoCliente > 0) {
            $montoCuota = round($saldoCliente / $numeroCuotas, 2);
            $primera = filled($datos['fecha_primera_cuota'] ?? null)
                ? Carbon::parse($datos['fecha_primera_cuota'])
                : now()->addMonth();
            $restante = $saldoCliente;

            for ($i = 1; $i <= $numeroCuotas; $i++) {
                $monto = $i === $numeroCuotas ? $restante : $montoCuota;
                $restante = round($restante - $monto, 2);

                $cuotas[] = [
                    'numero' => $i,
                    'fecha' => $primera->copy()->addMonthsNoOverflow($i - 1),
                    'monto' => $monto,
                    'nuevo_saldo' => max($restante, 0),
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
        return collect(['iguala', 'patrocinio_hih', 'porcentaje_patrocinio', 'anticipo', 'numero_cuotas', 'fecha_primera_cuota'])
            ->mapWithKeys(fn (string $campo) => [$campo => $get($campo)])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    protected static function resumenPlanHtml(array $plan): HtmlString
    {
        $bs = fn (float $monto): string => 'Bs. '.number_format($monto, 2);
        $fila = fn (string $a, string $b, string $c, string $d): string => '<tr>'
            .'<td style="padding:4px 8px;">'.e($a).'</td>'
            .'<td style="padding:4px 8px;">'.e($b).'</td>'
            .'<td style="padding:4px 8px;text-align:right;">'.e($c).'</td>'
            .'<td style="padding:4px 8px;text-align:right;">'.e($d).'</td></tr>';

        $lineas = ['Iguala profesional: <strong>'.e($bs($plan['iguala'])).'</strong>'];

        if ($plan['patrocinio']) {
            $porcentaje = rtrim(rtrim(number_format($plan['porcentaje'], 2), '0'), '.');
            $lineas[] = 'Hand in Hand ('.e($porcentaje).'%): '.e($bs($plan['monto_patrocinio']));
        }

        $lineas[] = 'Iguala Netley: <strong>'.e($bs($plan['monto_cliente'])).'</strong>';
        $lineas[] = 'Anticipo: '.e($bs($plan['pago_hoy']));
        $lineas[] = 'Saldo: <strong>'.e($bs($plan['saldo_cliente'])).'</strong>';

        $tabla = '';

        if ($plan['pago_hoy'] > 0 || $plan['cuotas'] !== []) {
            $tabla = '<table style="margin-top:.5rem;border-collapse:collapse;font-size:.875rem;">'
                .'<thead><tr style="text-align:left;border-bottom:1px solid rgb(128 128 128 / .3);">'
                .'<th style="padding:4px 8px;">Cuota</th><th style="padding:4px 8px;">Fecha</th>'
                .'<th style="padding:4px 8px;text-align:right;">Monto</th><th style="padding:4px 8px;text-align:right;">Saldo</th></tr></thead><tbody>';

            if ($plan['pago_hoy'] > 0) {
                $tabla .= $fila('0 (anticipo, hoy)', now()->format('d/m/Y'), $bs($plan['pago_hoy']), $bs($plan['saldo_cliente']));
            }

            foreach ($plan['cuotas'] as $cuota) {
                $tabla .= $fila((string) $cuota['numero'], $cuota['fecha']->format('d/m/Y'), $bs($cuota['monto']), $bs($cuota['nuevo_saldo']));
            }

            $tabla .= '</tbody></table>';
        }

        return new HtmlString('<div style="line-height:1.7;">'.implode('<br>', $lineas).'</div>'.$tabla);
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
}
