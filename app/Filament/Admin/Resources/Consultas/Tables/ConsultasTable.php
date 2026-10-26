<?php

namespace App\Filament\Admin\Resources\Consultas\Tables;

use App\Enums\EstadoConsulta;
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
                    ->toggleable()
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) !== 'agendados'),
                TextColumn::make('fecha_consulta')
                    ->date('d/m/Y')
                    ->sortable()
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) !== 'agendados'),
                // En la pestaña "Agendados" se muestran los datos de la cita
                // vigente (ver ListConsultas, que precarga `citas`) en vez de
                // los de la consulta.
                TextColumn::make('citaVigente.fecha')
                    ->label('Fecha de cita')
                    ->state(fn (Consulta $record) => $record->citas->first()?->fecha)
                    ->date('d/m/Y')
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) === 'agendados'),
                TextColumn::make('citaVigente.hora')
                    ->label('Hora')
                    ->state(fn (Consulta $record) => $record->citas->first()?->hora)
                    ->time()
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) === 'agendados'),
                TextColumn::make('citaVigente.personal')
                    ->label('Abogado(s)')
                    ->state(fn (Consulta $record) => $record->citas->first()?->personal->pluck('nombres')->all() ?? [])
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->placeholder('Sin asignar')
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) === 'agendados'),
                TextColumn::make('citaVigente.detalle')
                    ->label('Detalle de la cita')
                    ->state(fn (Consulta $record) => $record->citas->first()?->detalle)
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable()
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) === 'agendados'),
            ])
            ->defaultSort('fecha_consulta', 'desc')
            ->filters([
                SelectFilter::make('estado')->options(EstadoConsulta::class),
                ...FiltrosContacto::make(),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                EditAction::make()->label('Ver'),
                ReactivarConsultaAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
                ]),
            ]);
    }
}
