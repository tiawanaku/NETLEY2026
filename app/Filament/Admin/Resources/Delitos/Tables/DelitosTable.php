<?php

namespace App\Filament\Admin\Resources\Delitos\Tables;

use App\Enums\Especialidad;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DelitosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('area')
                    ->label('Área')
                    ->badge()
                    ->sortable(),
                TextColumn::make('delito')
                    ->label('Delito / Materia específica')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
            ])
            ->defaultSort('area')
            ->filters([
                SelectFilter::make('area')
                    ->label('Área')
                    ->options(fn () => collect(Especialidad::cases())->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()])),
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
