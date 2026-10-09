<?php

namespace App\Filament\Admin\Resources\Tallers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TallersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombres')->searchable(['nombres', 'ap_paterno', 'ap_materno']),
                TextColumn::make('telefono')->searchable(),
                TextColumn::make('materia_legal')->badge(),
                TextColumn::make('origen')->limit(30),
                TextColumn::make('fecha_programada')->date(),
            ])
            ->defaultSort('id', 'desc')
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
