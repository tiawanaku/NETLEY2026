<?php

namespace App\Filament\Admin\Resources\Llamadas\Tables;

use App\Enums\Especialidad;
use App\Filament\Admin\Resources\Llamadas\Actions\CrearConsultaAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LlamadasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->searchable(['nombres', 'ap_paterno', 'ap_materno']),
                TextColumn::make('telefono')->searchable(),
                TextColumn::make('materia_legal')->badge(),
                TextColumn::make('ciudad'),
                TextColumn::make('accion')->badge()->placeholder('-'),
                TextColumn::make('personal.nombres')->label('Asignado a'),
                TextColumn::make('fecha')->date(),
                TextColumn::make('intentos_llamada_count')
                    ->counts('intentosLlamada')
                    ->label('Intentos'),
                TextColumn::make('consulta_id')
                    ->label('Consulta')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : 'sin consulta'),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                SelectFilter::make('materia_legal')->options(Especialidad::class),
                SelectFilter::make('personal_id')
                    ->label('Asignado a')
                    ->relationship('personal', 'nombres')
                    ->searchable(),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                CrearConsultaAction::make(),
                EditAction::make()->visible(fn () => auth()->user()?->puede('editar')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
                ]),
            ]);
    }
}
