<?php

namespace App\Filament\Admin\Resources\Citas\Tables;

use App\Filament\Admin\Resources\Citas\Actions\AnularCitaAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CitasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')->date()->sortable(),
                TextColumn::make('hora')->time(),
                TextColumn::make('personal.nombres')->label('Abogado(s)')->listWithLineBreaks()->limitList(2),
                TextColumn::make('consulta.nombre_completo')->label('Consulta')->placeholder('-'),
                TextColumn::make('caso_id')->label('Caso')->badge()->formatStateUsing(fn ($state) => $state ? "#{$state}" : '-'),
                TextColumn::make('tipo'),
                TextColumn::make('detalle')->limit(40),
                IconColumn::make('anulada')->boolean(),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                SelectFilter::make('personal')
                    ->relationship('personal', 'nombres')
                    ->searchable(),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                AnularCitaAction::make(),
                EditAction::make()->visible(fn () => auth()->user()?->puede('editar')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
                ]),
            ]);
    }
}
