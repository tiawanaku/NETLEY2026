<?php

namespace App\Filament\Admin\Resources\Casos\RelationManagers;

use App\Filament\Admin\Support\CamposPago;
use App\Models\Caso;
use App\Models\Pago;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagos';

    protected static ?string $title = 'Pagos';

    public function form(Schema $schema): Schema
    {
        return $schema->inlineLabel()->columns(1)->components([
            TextInput::make('monto')->numeric()->prefix('Bs.')->required(),
            ...CamposPago::components(),
            DatePicker::make('fecha_pago')->default(now())->required(),
            Select::make('sucursal')
                ->options(['LA PAZ' => 'La Paz', 'SANTA CRUZ' => 'Santa Cruz', 'COCHABAMBA' => 'Cochabamba'])
                ->default('LA PAZ')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nro_recibo')
            ->columns([
                TextColumn::make('nro_recibo')->label('Recibo #'),
                TextColumn::make('nro_cuota')->label('Cuota #'),
                TextColumn::make('monto')->money('BOB'),
                TextColumn::make('fecha_pago')->date(),
                TextColumn::make('registrado_por'),
            ])
            ->defaultSort('fecha_pago', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar pago')
                    ->mutateDataUsing(function (array $data): array {
                        $caso = $this->getOwnerRecord();

                        $data['cliente_id'] = $caso->cliente_id;
                        $data['nro_recibo'] = ((int) Pago::max('nro_recibo')) + 1;
                        $data['nro_cuota'] = Pago::where('caso_id', $caso->id)->count() + 1;
                        $data['registrado_por'] = auth()->user()?->username;

                        return $data;
                    }),
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
            ->recordActions([
                Action::make('recibo')
                    ->label('Recibo PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn (Pago $record) => response()->streamDownload(
                        fn () => print (Pdf::loadView('pdf.recibo-pago', ['pago' => $record->load(['cliente', 'caso'])])->output()),
                        "recibo-{$record->nro_recibo}.pdf"
                    )),
            ]);
    }
}
