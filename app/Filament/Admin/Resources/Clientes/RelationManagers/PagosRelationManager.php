<?php

namespace App\Filament\Admin\Resources\Clientes\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagos';

    protected static ?string $title = 'Pagos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nro_recibo')
            ->columns([
                TextColumn::make('nro_recibo')->label('Recibo #'),
                TextColumn::make('caso_id')->label('Caso #'),
                TextColumn::make('nro_cuota')->label('Cuota #'),
                TextColumn::make('monto')->money('BOB'),
                TextColumn::make('fecha_pago')->date(),
                TextColumn::make('registrado_por'),
            ])
            ->defaultSort('fecha_pago', 'desc');
    }
}
