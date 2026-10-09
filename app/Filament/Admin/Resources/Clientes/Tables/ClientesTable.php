<?php

namespace App\Filament\Admin\Resources\Clientes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClientesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nro_cliente')
                    ->label('N° de cliente')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->searchable(['nombres', 'ap_paterno', 'ap_materno'])
                    ->sortable(['nombres']),
                TextColumn::make('ci')
                    ->label('CI')
                    ->searchable(),
                TextColumn::make('telefono')
                    ->searchable(),
                TextColumn::make('sucursal')
                    ->badge(),
                TextColumn::make('casos_count')
                    ->label('Casos')
                    ->counts('casos')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('sucursal')
                    ->options(['LA PAZ' => 'La Paz', 'SANTA CRUZ' => 'Santa Cruz', 'COCHABAMBA' => 'Cochabamba']),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                EditAction::make()->visible(fn () => auth()->user()?->puede('editar')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
                ]),
            ]);
    }
}
