<?php

namespace App\Filament\Admin\Resources\Casos\Tables;

use App\Enums\Especialidad;
use App\Enums\EstadoCaso;
use App\Filament\Admin\Resources\Casos\Actions\CasoActions;
use App\Models\Caso;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CasosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cliente.nombre_completo')
                    ->label('Cliente')
                    ->searchable(['nombres', 'ap_paterno']),
                TextColumn::make('especialidad')->badge(),
                TextColumn::make('delito.delito')
                    ->label('Delito')
                    ->limit(35)
                    ->placeholder(fn (Caso $record) => $record->delito_texto ?: '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('estado')
                    ->badge(),
                TextColumn::make('saldo')->money('BOB')->sortable(),
                TextColumn::make('personal.nombres')
                    ->label('Abogado(s)')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fecha_fin')
                    ->label('Vencimiento')
                    ->date()
                    ->sortable()
                    ->color(fn (Caso $record) => $record->fecha_fin && $record->fecha_fin->isPast() && $record->estado !== EstadoCaso::Cerrado ? 'danger' : null),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('especialidad')->options(Especialidad::class),
                SelectFilter::make('estado')->options(EstadoCaso::class),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                CasoActions::verCliente(),
                ActionGroup::make([
                    EditAction::make()->visible(fn () => auth()->user()?->puede('editar')),
                    CasoActions::informeRapido(),
                    CasoActions::cerrar(),
                    CasoActions::reactivar(),
                    CasoActions::informeCierrePdf(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
                ]),
            ]);
    }
}
