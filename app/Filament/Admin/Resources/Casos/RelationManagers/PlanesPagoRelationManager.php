<?php

namespace App\Filament\Admin\Resources\Casos\RelationManagers;

use App\Enums\EstadoCuota;
use App\Filament\Admin\Support\CamposPago;
use App\Filament\Admin\Support\PlanPagoRecalculo;
use App\Models\Caso;
use App\Models\Pago;
use App\Models\PlanPago;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanesPagoRelationManager extends RelationManager
{
    protected static string $relationship = 'planesPago';

    protected static ?string $title = 'Plan de pagos';

    public function form(Schema $schema): Schema
    {
        return $schema->inlineLabel()->columns(1)->components([
            TextInput::make('numero')->label('N° de cuota')->numeric()->required(),
            DatePicker::make('fecha')->required(),
            TextInput::make('monto')->numeric()->prefix('Bs.')->required(),
            TextInput::make('nuevo_saldo')->numeric()->prefix('Bs.')->label('Saldo tras esta cuota'),
            Select::make('estado')->options(EstadoCuota::class)->default(EstadoCuota::Pendiente)->required(),
        ]);
    }

    /**
     * Arriba de la tabla, el resumen completo del paso 3 ("Pago y plan de
     * cuotas") con el que se creó el caso (ver resumen-pago.blade.php).
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_RELATION_MANAGER_BEFORE),
                View::make('filament.admin.casos.resumen-pago')
                    ->viewData(fn (): array => $this->resumenPago()),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_RELATION_MANAGER_AFTER),
            ]);
    }

    /**
     * Mismo "Resumen" que el paso 3 (ClienteCasoWizard::resumenPlanHtml()),
     * armado con lo que quedó guardado en el caso y su plan de pagos, y al
     * lado los montos de "Pago de servicios extra".
     *
     * @return array{resumen: \Illuminate\Support\HtmlString, servicios: array<int, array<string, string>>}
     */
    protected function resumenPago(): array
    {
        /** @var Caso $caso */
        $caso = $this->getOwnerRecord()->refresh();
        $bs = fn ($monto): string => 'Bs. '.(fmod((float) $monto, 1.0) == 0.0
            ? number_format((float) $monto, 0, '.', '')
            : number_format((float) $monto, 2, '.', ''));

        $hih = $caso->patrocinio_hih ? (float) $caso->monto_patrocinio : 0.0;
        $anticipo = (float) $caso->pagos()->where('tipo_pago', 'anticipo')->sum('monto');
        $plan = [
            'iguala' => (float) $caso->iguala + $hih,
            'patrocinio' => (bool) $caso->patrocinio_hih,
            'porcentaje' => (float) $caso->porcentaje_patrocinio,
            'monto_patrocinio' => $hih,
            'monto_cliente' => (float) $caso->iguala,
            'pago_hoy' => $anticipo,
            'saldo_cliente' => (float) $caso->iguala - $anticipo,
            // Sin la lista de cuotas: ya está en la tabla del Plan de pagos de abajo.
            'cuotas' => [],
        ];

        $servicios = collect([
            [$caso->servicio_psicologia, 'Psicología', $caso->monto_servicio_psicologia],
            [$caso->servicio_trabajo_social, 'Trabajo social', $caso->monto_servicio_trabajo_social],
            [$caso->servicio_otro, 'Otro'.($caso->servicio_otro_detalle ? ': '.$caso->servicio_otro_detalle : ''), $caso->monto_servicio_otro],
        ])
            ->filter(fn (array $servicio) => (bool) $servicio[0])
            ->map(fn (array $servicio) => ['nombre' => $servicio[1], 'monto' => $servicio[2] !== null ? $bs($servicio[2]) : '—'])
            ->values()
            ->all();

        return [
            'resumen' => \App\Filament\Admin\Support\ClienteCasoWizard::resumenPlanHtml($plan, ' (anticipo)'),
            'servicios' => $servicios,
        ];
    }

    /** El pago (último) con el que se cubrió esta cuota. */
    protected static function pagoDeCuota(PlanPago $record): ?Pago
    {
        return Pago::where('caso_id', $record->caso_id)
            ->where('nro_cuota', $record->numero)
            ->latest('id')
            ->first();
    }

    /** ¿Esta cuota se pagó con el pago marcado como anticipo? */
    protected static function esAnticipo(PlanPago $record): bool
    {
        return Pago::where('caso_id', $record->caso_id)
            ->where('nro_cuota', $record->numero)
            ->where('tipo_pago', 'anticipo')
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero')
            ->columns([
                // La cuota pagada con el anticipo (al crear el caso) se marca como tal.
                TextColumn::make('numero')
                    ->label('Cuota #')
                    ->formatStateUsing(fn (int $state, PlanPago $record) => match (true) { $state === 0 => 'Anticipo', self::esAnticipo($record) => $state.' · Anticipo', default => $state }),
                TextColumn::make('fecha')->date(),
                // Montos enteros, sin "BOB", decimales ni separador de miles;
                // la moneda va en la cabecera.
                TextColumn::make('monto')->label('Monto Bs.')->formatStateUsing(fn ($state) => $state === null ? '—' : number_format((float) $state, 0, '', '')),
                TextColumn::make('nuevo_saldo')->label('Saldo restante Bs.')->formatStateUsing(fn ($state) => $state === null ? '—' : number_format((float) $state, 0, '', '')),
                TextColumn::make('estado')->badge(),
            ])
            ->defaultSort('numero')
            ->headerActions([
                Action::make('imprimirPlanPago')
                    ->label('Imprimir plan de pago')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->action(function (): mixed {
                        /** @var Caso $caso */
                        $caso = $this->getOwnerRecord()->load('cliente');

                        return response()->streamDownload(
                            fn () => print (Pdf::loadView('pdf.plan-pago-caso', [
                                'caso' => $caso,
                                'planes' => $caso->planesPago()->orderBy('numero')->get(),
                            ])->output()),
                            "plan-pago-caso-{$caso->id}.pdf"
                        );
                    }),
                // Pasó acá desde la tabla "Pagos", que se quitó.
                Action::make('historialPagos')
                    ->label('Imprimir historial de pagos')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->action(function (): mixed {
                        /** @var Caso $caso */
                        $caso = $this->getOwnerRecord()->load('cliente');

                        return response()->streamDownload(
                            fn () => print (Pdf::loadView('pdf.historial-pagos-caso', [
                                'caso' => $caso,
                                'pagos' => $caso->pagos()->orderBy('fecha_pago')->get(),
                            ])->output()),
                            "historial-pagos-caso-{$caso->id}.pdf"
                        );
                    }),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                // Plan dinámico (ver PlanPagoRecalculo): se paga en orden (solo
                // la primera cuota pendiente), sin pasar del saldo, y la
                // última cuota debe cubrir todo lo que falte. Si el monto es
                // distinto al asignado, el resto de cuotas se recalcula solo.
                Action::make('realizarPago')
                    ->label('Realizar pago')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (PlanPago $record) => $record->estado === EstadoCuota::Pendiente)
                    ->disabled(fn (PlanPago $record) => PlanPagoRecalculo::proximaCuota($record->caso)?->id !== $record->id)
                    ->tooltip(fn (PlanPago $record) => PlanPagoRecalculo::proximaCuota($record->caso)?->id !== $record->id
                        ? 'Las cuotas se pagan en orden: primero la cuota '.PlanPagoRecalculo::proximaCuota($record->caso)?->numero
                        : null)
                    ->modalHeading(fn (PlanPago $record) => 'Pagar cuota '.$record->numero)
                    ->modalSubmitActionLabel('Guardar')
                    ->modalCancelActionLabel('Cancelar')
                    ->schema([
                        TextInput::make('monto')
                            ->label('Monto a pagar')
                            ->numeric()
                            ->integer()
                            ->prefix('Bs.')
                            ->required()
                            ->live(onBlur: true)
                            ->default(fn (PlanPago $record) => PlanPagoRecalculo::esUltimaPendiente($record)
                                ? (float) $record->caso->saldo
                                : (float) $record->monto)
                            ->rule(fn (PlanPago $record) => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                                try {
                                    PlanPagoRecalculo::validarPago($record, (float) $value, (float) $record->caso->fresh()->saldo);
                                } catch (\Illuminate\Validation\ValidationException $e) {
                                    $fail(collect($e->errors())->flatten()->first());
                                }
                            })
                            // Vista previa de cómo queda el resto del plan.
                            ->helperText(function (PlanPago $record, $state): string {
                                $asignado = (float) $record->monto;
                                $saldo = (float) $record->caso->saldo;

                                if (PlanPagoRecalculo::esUltimaPendiente($record)) {
                                    return 'Última cuota: debe cubrir todo el saldo pendiente (Bs. '.PlanPagoRecalculo::bs($saldo).').';
                                }

                                $monto = (float) ($state ?: 0);

                                if ($monto <= 0 || abs($monto - $asignado) < 0.001) {
                                    return 'Monto asignado: Bs. '.PlanPagoRecalculo::bs($asignado).'. Si paga otro monto, las demás cuotas se recalculan.';
                                }

                                $vista = PlanPagoRecalculo::vistaPrevia($record, $monto);

                                if ($vista['saldo'] <= 0) {
                                    return 'Con este pago se cancela todo el saldo: las demás cuotas pendientes se quitan.';
                                }

                                return 'Saldo después del pago: Bs. '.PlanPagoRecalculo::bs($vista['saldo']).'. Las '.$vista['restantes'].' cuotas restantes quedarán en Bs. '
                                    .$vista['monto_cuota'].($vista['ultima'] != $vista['monto_cuota'] ? ' (la última Bs. '.PlanPagoRecalculo::bs($vista['ultima']).')' : '').'.';
                            }),
                        ...CamposPago::components(),
                        DatePicker::make('fecha_pago')->label('Fecha de pago')->default(now())->required(),
                        Select::make('sucursal')
                            ->options(['LA PAZ' => 'La Paz', 'SANTA CRUZ' => 'Santa Cruz', 'COCHABAMBA' => 'Cochabamba'])
                            ->default('LA PAZ')
                            ->required(),
                    ])
                    ->action(function (PlanPago $record, array $data): void {
                        PlanPagoRecalculo::pagarCuota($record, (float) $data['monto'], [
                            ...CamposPago::datos($data),
                            'fecha_pago' => $data['fecha_pago'],
                            'sucursal' => $data['sucursal'],
                        ]);

                        Notification::make()->title('Pago registrado')->success()->send();
                    }),
                // Corregir un pago ya registrado: primero se pide la
                // justificación del error y recién después se habilitan los
                // datos del pago. Queda en el historial (PagoCorreccion).
                Action::make('editarPago')
                    ->label('Editar pago')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (PlanPago $record) => $record->estado === EstadoCuota::Pagado
                        && self::pagoDeCuota($record) !== null
                        && auth()->user()?->puede('editar'))
                    ->modalHeading(fn (PlanPago $record) => 'Editar pago — cuota '.$record->numero)
                    ->modalWidth('2xl')
                    ->modalSubmitActionLabel('Guardar')
                    ->modalCancelActionLabel('Cancelar')
                    ->fillForm(function (PlanPago $record): array {
                        $pago = self::pagoDeCuota($record);

                        return [
                            'monto' => (float) $pago->monto,
                            'forma_pago' => $pago->forma_pago,
                            'nro_cheque' => $pago->nro_cheque,
                            'banco' => $pago->banco,
                            'fecha_pago' => $pago->fecha_pago,
                            'sucursal' => $pago->sucursal,
                        ];
                    })
                    ->steps([
                        Step::make('Justificación')
                            ->description('Motivo de la corrección')
                            ->schema([
                                Placeholder::make('pago_actual')
                                    ->label('Pago registrado')
                                    ->content(function (PlanPago $record): string {
                                        $pago = self::pagoDeCuota($record);

                                        return 'Recibo N° '.$pago->nro_recibo.' · Bs. '.PlanPagoRecalculo::bs((float) $pago->monto)
                                            .' · '.$pago->fecha_pago?->format('d/m/Y').' · '.ucfirst((string) $pago->forma_pago);
                                    }),
                                Textarea::make('justificacion')
                                    ->label('Justificación del error')
                                    ->placeholder('Explica qué estaba mal en este pago y por qué se corrige.')
                                    ->required()
                                    ->minLength(10)
                                    ->rows(3),
                            ]),
                        Step::make('Datos del pago')
                            ->description('Corregir')
                            ->schema([
                                TextInput::make('monto')
                                    ->label('Monto')
                                    ->numeric()
                                    ->prefix('Bs.')
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(fn (PlanPago $record) => (float) self::pagoDeCuota($record)->monto + (float) $record->caso->saldo)
                                    ->validationMessages(['max' => 'Con ese monto se pagaría más que la Iguala Netley (máximo Bs. :max).'])
                                    ->helperText('Si cambia el monto, el saldo y las cuotas pendientes se recalculan.'),
                                ...CamposPago::components(conTipo: false),
                                DatePicker::make('fecha_pago')->label('Fecha de pago')->required(),
                                Select::make('sucursal')
                                    ->options(['LA PAZ' => 'La Paz', 'SANTA CRUZ' => 'Santa Cruz', 'COCHABAMBA' => 'Cochabamba'])
                                    ->required(),
                            ]),
                    ])
                    ->action(function (PlanPago $record, array $data): void {
                        $pago = self::pagoDeCuota($record);
                        $forma = CamposPago::datos($data, tipoFijo: $pago->tipo_pago);

                        PlanPagoRecalculo::corregirPago($pago, [
                            'monto' => (float) $data['monto'],
                            'fecha_pago' => $data['fecha_pago'],
                            'forma_pago' => $forma['forma_pago'],
                            'nro_cheque' => $forma['nro_cheque'],
                            'banco' => $forma['banco'],
                            'sucursal' => $data['sucursal'],
                        ], $data['justificacion']);

                        Notification::make()->title('Pago corregido')->body('La corrección quedó registrada con su justificación.')->success()->send();
                    }),
                Action::make('imprimirRecibo')
                    ->label('Imprimir recibo')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn (PlanPago $record) => $record->estado === EstadoCuota::Pagado)
                    ->action(function (PlanPago $record) {
                        $pago = Pago::where('caso_id', $record->caso_id)
                            ->where('nro_cuota', $record->numero)
                            ->latest('id')
                            ->first();

                        if (! $pago) {
                            Notification::make()->title('No se encontró el pago de esta cuota')->danger()->send();

                            return null;
                        }

                        return response()->streamDownload(
                            fn () => print (Pdf::loadView('pdf.recibo-pago', ['pago' => $pago->load(['cliente', 'caso'])])->output()),
                            "recibo-{$pago->nro_recibo}.pdf"
                        );
                    }),
            ]);
    }
}
