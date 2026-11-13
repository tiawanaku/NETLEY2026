<?php

namespace App\Filament\Admin\Resources\Casos\Tables;

use App\Enums\Especialidad;
use App\Enums\EstadoCaso;
use App\Filament\Admin\Resources\Casos\Actions\CasoActions;
use App\Filament\Admin\Support\FiltrosContacto;
use App\Models\Caso;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use App\Filament\Admin\Support\Eliminacion;
use Filament\Notifications\Notification;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
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
                TextColumn::make('especialidad')->label('Materia legal')->badge()->state(fn ($record) => $record->especialidad ?? $record->materia_texto),
                TextColumn::make('delito.delito')
                    ->label('Delito')
                    ->limit(35)
                    ->placeholder(fn (Caso $record) => $record->delito_texto ?: '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('estado')
                    ->badge(),
                IconColumn::make('documentos_pendientes')
                    ->label('Documento')
                    ->icon(fn (bool $state) => $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->color(fn (bool $state) => $state ? 'warning' : 'gray')
                    ->tooltip(fn (bool $state) => $state ? 'Falta subir el documento del expediente' : 'Documento subido')
                    ->toggleable(),
                TextColumn::make('saldo')->money('BOB')->sortable(),
                TextColumn::make('personal.nombres')
                    ->label('Abogado(s)')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->toggleable(isToggledHiddenByDefault: true),
                // Solo en "Casos eliminados".
                TextColumn::make('fecha_eliminacion')
                    ->label('Eliminado el')
                    ->date('d/m/Y')
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) === 'eliminados'),
                TextColumn::make('motivo_eliminacion')
                    ->label('Motivo')
                    ->wrap()
                    ->limit(80)
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) === 'eliminados'),
                TextColumn::make('fecha_fin')
                    ->label('Vencimiento')
                    ->date()
                    ->sortable()
                    ->color(fn (Caso $record) => $record->fecha_fin && $record->fecha_fin->isPast() && $record->estado !== EstadoCaso::Cerrado ? 'danger' : null),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('especialidad')->options(Especialidad::class),
                SelectFilter::make('estado')->options([EstadoCaso::Activo->value => EstadoCaso::Activo->getLabel(), EstadoCaso::Cerrado->value => EstadoCaso::Cerrado->getLabel()]),
                // Datos de contacto del cliente del caso.
                ...FiltrosContacto::make(relacion: 'cliente'),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                CasoActions::verCliente()->label('Ver cliente')->hidden(fn (Caso $record) => $record->trashed()),
                // La ficha (también la de un caso eliminado, para revisarlo).
                EditAction::make()->label('Ver caso')->icon('heroicon-o-eye'),
                CasoActions::descargarDocumentoEliminacion()->label('Documento'),
                CasoActions::restaurar(),
                ActionGroup::make([
                    CasoActions::informeRapido(),
                    CasoActions::cerrar(),
                    CasoActions::reactivar(),
                    CasoActions::informeCierrePdf(),
                    CasoActions::eliminar(),
                ])->hidden(fn (Caso $record) => $record->trashed()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Borrado lógico: van a "Casos eliminados".
                    // Misma fecha y justificación para todos los seleccionados.
                    BulkAction::make('eliminarSeleccionados')
                        ->label('Eliminar seleccionados')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn ($livewire) => auth()->user()?->puede('eliminar') && ($livewire->activeTab ?? null) !== 'eliminados')
                        ->modalHeading('¿Eliminar los casos seleccionados?')
                        ->modalDescription('Pasan a "Casos eliminados" y se pueden restaurar desde ahí.')
                        ->modalSubmitActionLabel('Eliminar')
                        ->modalCancelActionLabel('Cancelar')
                        ->fillForm(fn (): array => Eliminacion::valoresIniciales())
                        ->schema(Eliminacion::campos())
                        ->action(function ($records, array $data): void {
                            $records->each(fn (Caso $caso) => Eliminacion::eliminar($caso, $data));
                            Notification::make()->title('Casos eliminados')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
