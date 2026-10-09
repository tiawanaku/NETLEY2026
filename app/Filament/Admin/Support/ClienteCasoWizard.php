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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Los 3 pasos "Validación de datos" / "Proceso" / "Pago y plan de cuotas" y
 * la lógica de creación de cliente + caso + pago + plan de cuotas,
 * compartidos entre AscenderCasoAction (convertir una consulta en cliente
 * ejecutivo) y CreateCliente (dar de alta un cliente directamente con el
 * mismo flujo completo).
 */
class ClienteCasoWizard
{
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
                        ])
                        ->native(false)
                        ->extraAttributes(['data-enter-nav-field' => 'true']),
                    DatePicker::make('fecha_nacimiento')->label('Fecha de nacimiento')->maxDate(now())->extraInputAttributes(['data-enter-nav' => 'true']),
                    TextInput::make('direccion')->label('Dirección')->columnSpanFull()->extraInputAttributes(['data-enter-nav' => 'true']),
                ])
                ->columns(2)
                ->inlineLabel(),

            Step::make('Proceso')
                ->schema([
                    ...DelitoSelect::make(areaLabel: 'Materia legal'),
                    Textarea::make('descripcion')->columnSpanFull()->inlineLabel(false)->extraInputAttributes(['data-enter-nav' => 'true']),
                    TextInput::make('apersonamiento')->datalist(['Demandante', 'Demandado', 'Solicitante'])->extraInputAttributes(['data-enter-nav' => 'true']),
                    Select::make('personal')
                        ->label('Abogado(s) asignado(s)')
                        ->options(fn (Get $get) => PersonalEspecialidades::personalOptionsPlain(
                            $get('especialidad'),
                            (array) ($get('personal') ?? []),
                            'Abogado'
                        ))
                        ->multiple()
                        ->searchable()
                        ->extraAttributes(['data-enter-nav-field' => 'true']),
                    DatePicker::make('fecha_inicio')->default(now())->required()->live()->extraInputAttributes(['data-enter-nav' => 'true']),
                    TextInput::make('duracion_meses')
                        ->label('Duración del proceso (meses)')
                        ->numeric()
                        ->minValue(1)
                        ->live()
                        ->extraInputAttributes(['data-enter-nav' => 'true']),
                    Placeholder::make('fecha_fin_preview')
                        ->label('Fecha estimada de fin')
                        ->content(function (Get $get): string {
                            $inicio = $get('fecha_inicio');
                            $meses = $get('duracion_meses');

                            if (blank($inicio) || blank($meses)) {
                                return '—';
                            }

                            return Carbon::parse($inicio)->addMonthsNoOverflow((int) $meses)->translatedFormat('d/m/Y');
                        }),
                ])
                ->columns(2)
                ->inlineLabel(),

            Step::make('Pago y plan de cuotas')
                ->schema([
                    TextInput::make('iguala')->label('Monto total (iguala)')->numeric()->prefix('Bs.')->required()->live()->extraInputAttributes(['data-enter-nav' => 'true']),
                    TextInput::make('anticipo')->label('Anticipo / primer pago')->numeric()->prefix('Bs.')->default(0)->live()->extraInputAttributes(['data-enter-nav' => 'true']),
                    ...CamposPago::components(conTipo: false),
                    TextInput::make('numero_cuotas')->label('N° de cuotas restantes')->numeric()->default(0)->minValue(0)->live()->extraInputAttributes(['data-enter-nav' => 'true']),
                    DatePicker::make('fecha_primera_cuota')->default(now()->addMonth())->extraInputAttributes(['data-enter-nav' => 'true']),
                    Placeholder::make('saldo_preview')
                        ->label('Saldo y plan resultante')
                        ->content(function (Get $get): string {
                            $iguala = (float) ($get('iguala') ?? 0);
                            $anticipo = (float) ($get('anticipo') ?? 0);
                            $cuotas = (int) ($get('numero_cuotas') ?? 0);
                            $saldo = max($iguala - $anticipo, 0);

                            $texto = 'Saldo pendiente: Bs. '.number_format($saldo, 2);

                            if ($cuotas > 0) {
                                $texto .= ' — '.$cuotas.' cuota(s) de Bs. '.number_format($saldo / $cuotas, 2).' c/u';
                            }

                            return $texto;
                        })
                        ->columnSpanFull(),
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
            $iguala = (float) $data['iguala'];
            $anticipo = (float) ($data['anticipo'] ?? 0);
            $numeroCuotas = (int) ($data['numero_cuotas'] ?? 0);
            $saldo = max($iguala - $anticipo, 0);

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
                    'extension' => $data['extension'] ?? null,
                ]
            );

            $duracionMeses = filled($data['duracion_meses'] ?? null) ? (int) $data['duracion_meses'] : null;
            $fechaFin = $duracionMeses
                ? Carbon::parse($data['fecha_inicio'])->addMonthsNoOverflow($duracionMeses)->toDateString()
                : null;

            // saldo/pagado arrancan en el monto total; el pago del anticipo (si existe)
            // los ajusta automáticamente vía PagoObserver al crearse más abajo.
            $caso = Caso::create([
                'cliente_id' => $cliente->id,
                'especialidad' => $data['especialidad'],
                'delito_id' => $data['delito_id'] ?? null,
                'descripcion' => $data['descripcion'] ?? null,
                'apersonamiento' => $data['apersonamiento'] ?? null,
                'iguala' => $iguala,
                'saldo' => $iguala,
                'pagado' => 0,
                'fecha_inicio' => $data['fecha_inicio'],
                'duracion_meses' => $duracionMeses,
                'fecha_fin' => $fechaFin,
                'estado' => EstadoCaso::ActivoPendiente,
            ]);

            $cliente->increment('nro_casos');

            if (filled($data['personal'] ?? null)) {
                $caso->personal()->sync($data['personal']);
            }

            if ($anticipo > 0) {
                $siguienteRecibo = ((int) Pago::max('nro_recibo')) + 1;

                Pago::create([
                    'caso_id' => $caso->id,
                    'cliente_id' => $cliente->id,
                    'monto' => $anticipo,
                    ...CamposPago::datos($data, tipoFijo: 'anticipo'),
                    'fecha_pago' => now()->toDateString(),
                    'nro_cuota' => 0,
                    'nro_recibo' => $siguienteRecibo,
                    'registrado_por' => auth()->user()?->username,
                ]);

                // Cuota 0 del plan de pagos = el anticipo, ya pagado, para
                // que aparezca junto con el resto de las cuotas.
                $caso->planesPago()->create([
                    'numero' => 0,
                    'fecha' => now()->toDateString(),
                    'monto' => $anticipo,
                    'nuevo_saldo' => $saldo,
                    'estado' => 'pagado',
                    'creado_por' => auth()->user()?->username,
                ]);
            }

            if ($numeroCuotas > 0) {
                $montoCuota = round($saldo / $numeroCuotas, 2);
                $fecha = Carbon::parse($data['fecha_primera_cuota'] ?? now()->addMonth());
                $saldoRestante = $saldo;

                for ($i = 1; $i <= $numeroCuotas; $i++) {
                    $saldoRestante = round($saldoRestante - $montoCuota, 2);

                    $caso->planesPago()->create([
                        'numero' => $i,
                        'fecha' => $fecha->copy()->addMonthsNoOverflow($i - 1)->toDateString(),
                        'monto' => $montoCuota,
                        'nuevo_saldo' => max($saldoRestante, 0),
                        'estado' => 'pendiente',
                        'creado_por' => auth()->user()?->username,
                    ]);
                }
            }

            return ['cliente' => $cliente, 'caso' => $caso];
        });
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
