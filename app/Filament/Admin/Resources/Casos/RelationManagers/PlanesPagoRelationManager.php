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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
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

    /** @return array{filas: array<int, array<string, string>>, servicios: array<int, array<string, string>>} */
    protected function resumenPago(): array
    {
        /** @var Caso $caso */
        $caso = $this->getOwnerRecord()->refresh();
        $bs = fn ($monto): string => 'Bs. '.(fmod((float) $monto, 1.0) == 0.0
            ? number_format((float) $monto, 0, '.', '')
            : number_format((float) $monto, 2, '.', ''));
        $porcentaje = fn ($valor): string => rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.').'%';

        $hih = $caso->patrocinio_hih ? (float) $caso->monto_patrocinio : 0.0;
        $anticipo = (float) $caso->pagos()->where('tipo_pago', 'anticipo')->sum('monto');
        $cuotasAnticipo = $caso->pagos()->where('tipo_pago', 'anticipo')->pluck('nro_cuota')->map(fn ($n) => (int) $n)->all();
        $cuotas = $caso->planesPago()->orderBy('numero')->get()
            ->reject(fn (PlanPago $cuota) => (int) $cuota->numero === 0 || in_array((int) $cuota->numero, $cuotasAnticipo, true));
        $verde = 'color:#16a34a;';
        $naranja = 'color:#d97706;';

        $filas = [
            ['etiqueta' => 'Iguala profesional', 'valor' => $bs((float) $caso->iguala + $hih)],
            ['etiqueta' => 'Patrocinio HiH', 'valor' => $caso->patrocinio_hih
                ? $bs($hih).($caso->porcentaje_patrocinio ? ' ('.$porcentaje($caso->porcentaje_patrocinio).')' : '')
                : 'No'],
            ['etiqueta' => 'Iguala Netley', 'valor' => $bs($caso->iguala)],
            ['etiqueta' => 'Anticipo', 'valor' => $anticipo > 0 ? $bs($anticipo) : 'Sin anticipo'],
            ['etiqueta' => 'Saldo Netley', 'valor' => $bs((float) $caso->iguala - $anticipo)],
            ['etiqueta' => 'N° de cuotas mensuales', 'valor' => (string) $cuotas->count()],
            ['etiqueta' => 'Fecha de la primera cuota', 'valor' => $cuotas->first()?->fecha?->format('d/m/Y') ?? '—'],
            ['etiqueta' => 'Comisión (%)', 'valor' => (float) $caso->comision_porcentaje > 0 ? $porcentaje($caso->comision_porcentaje) : '—'],
            ['etiqueta' => 'Comisión (Bs.)', 'valor' => (float) $caso->comision_monto > 0 ? $bs($caso->comision_monto) : '—'],
            ['etiqueta' => 'Pagado a la fecha', 'valor' => $bs($caso->pagado), 'color' => $verde],
            ['etiqueta' => 'Saldo actual', 'valor' => $bs($caso->saldo), 'color' => $naranja],
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

        return ['filas' => $filas, 'servicios' => $servicios];
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
                TextColumn::make('monto')->money('BOB'),
                TextColumn::make('nuevo_saldo')->money('BOB')->label('Saldo restante'),
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
                Action::make('realizarPago')
                    ->label('Realizar pago')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (PlanPago $record) => $record->estado === EstadoCuota::Pendiente)
                    ->schema([
                        TextInput::make('monto')
                            ->numeric()
                            ->prefix('Bs.')
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(fn (PlanPago $record) => (float) $record->caso->saldo)
                            ->validationMessages(['max' => 'No puede pagar más del saldo pendiente (Bs. :max).'])
                            ->default(fn (PlanPago $record) => $record->monto),
                        ...CamposPago::components(),
                        DatePicker::make('fecha_pago')->default(now())->required(),
                        Select::make('sucursal')
                            ->options(['LA PAZ' => 'La Paz', 'SANTA CRUZ' => 'Santa Cruz', 'COCHABAMBA' => 'Cochabamba'])
                            ->default('LA PAZ')
                            ->required(),
                    ])
                    ->action(function (PlanPago $record, array $data): void {
                        $caso = $record->caso;
                        $montoOriginal = (float) $record->monto;
                        $monto = (float) $data['monto'];

                        Pago::create([
                            'caso_id' => $caso->id,
                            'cliente_id' => $caso->cliente_id,
                            'monto' => $monto,
                            ...CamposPago::datos($data),
                            'fecha_pago' => $data['fecha_pago'],
                            'nro_cuota' => $record->numero,
                            'nro_recibo' => ((int) Pago::max('nro_recibo')) + 1,
                            'sucursal' => $data['sucursal'],
                            'registrado_por' => auth()->user()?->username,
                        ]);

                        $record->update(['estado' => EstadoCuota::Pagado]);

                        // Si se pagó de más respecto a lo que correspondía a
                        // esta cuota, el excedente se descuenta del saldo y
                        // el plan de pagos restante se recalcula en partes
                        // iguales.
                        if ($monto > $montoOriginal) {
                            PlanPagoRecalculo::redistribuir($caso);
                        }

                        Notification::make()->title('Pago registrado')->success()->send();
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
