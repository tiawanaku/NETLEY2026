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
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero')
            ->columns([
                TextColumn::make('numero')->label('Cuota #')->formatStateUsing(fn (int $state) => $state === 0 ? 'Anticipo' : $state),
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
                EditAction::make()->visible(fn () => auth()->user()?->puede('editar')),
                DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
            ]);
    }
}
