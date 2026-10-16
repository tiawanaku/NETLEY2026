<?php

namespace App\Filament\Admin\Resources\Consultas\Tables;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Consultas\Actions\AscenderCasoAction;
use App\Filament\Admin\Resources\Consultas\Actions\ReactivarConsultaAction;
use App\Filament\Admin\Support\FiltrosContacto;
use App\Filament\Admin\Support\TelefonoColumna;
use App\Models\Consulta;
use App\Support\Origenes;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConsultasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('N° de consulta')
                    ->sortable(),
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->searchable(['nombres', 'ap_paterno', 'ap_materno']),
                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->formatStateUsing(fn (?string $state) => TelefonoColumna::html($state))
                    ->html()
                    ->searchable(),
                TextColumn::make('estado')
                    ->badge(),
                TextColumn::make('origen')
                    // "Talleres — <detalle del taller>".
                    ->formatStateUsing(fn (?string $state, Consulta $record): ?string => Origenes::texto($state, $record->origen_detalle))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fecha_consulta')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('fecha_consulta', 'desc')
            ->filters([
                SelectFilter::make('estado')->options(EstadoConsulta::class),
                ...FiltrosContacto::make(),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                EditAction::make()->label('Ver'),
                AscenderCasoAction::make(),
                ReactivarConsultaAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
                ]),
            ]);
    }
}
