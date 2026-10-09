<?php

namespace App\Filament\Admin\Resources\Clientes\Tables;

use App\Filament\Admin\Support\TelefonoColumna;
use App\Models\Cliente;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Abogado(s) y fechas del proceso salen del caso más reciente.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('ultimoCaso.personal'))
            ->columns([
                TextColumn::make('nro_cliente')
                    ->label('ID cliente')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->searchable(['nombres', 'ap_paterno', 'ap_materno'])
                    ->sortable(['nombres']),
                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->formatStateUsing(fn (?string $state) => TelefonoColumna::html($state))
                    ->html()
                    ->searchable(),
                TextColumn::make('abogados')
                    ->label('Abogado asignado')
                    ->state(fn (Cliente $record): array => $record->ultimoCaso?->personal
                        ->map(fn ($personal) => $personal->nombre_completo)
                        ->all() ?? [])
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextColumn::make('ultimoCaso.fecha_inicio')
                    ->label('Inicio del proceso')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('ultimoCaso.fecha_fin')
                    ->label('Fin del proceso')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('casos_count')
                    ->label('N° de casos')
                    ->counts('casos')
                    ->badge()
                    ->sortable(),
                TextColumn::make('ci')
                    ->label('CI')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sucursal')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
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
