<?php

namespace App\Filament\Admin\Resources\Personals\Tables;

use App\Enums\EstadoPersonal;
use App\Filament\Admin\Resources\Personals\Actions\PersonalActions;
use App\Filament\Admin\Resources\Personals\PersonalResource;
use App\Filament\Admin\Support\FiltrosContacto;
use App\Filament\Admin\Support\TelefonoColumna;
use App\Models\Personal;
use App\Support\PersonalEspecialidades;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class PersonalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Item')
                    ->sortable(),
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->searchable(['nombres', 'ap_paterno', 'ap_materno'])
                    ->sortable(['nombres']),
                TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->formatStateUsing(fn (?string $state) => TelefonoColumna::html($state))
                    ->html()
                    ->searchable(),
                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->formatStateUsing(fn (?string $state) => TelefonoColumna::html($state))
                    ->html()
                    ->searchable(),
                TextColumn::make('cargo')
                    ->label('Profesión')
                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Str::limit(is_array($state) ? implode(', ', $state) : (string) $state, 25))
                    ->searchable()
                    ->tooltip(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                TextColumn::make('especialidades')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PersonalEspecialidades::label($state)),
                TextColumn::make('fecha_inicio')
                    ->label('Fecha inicio')
                    ->date()
                    ->sortable(),
                TextColumn::make('fecha_fin')
                    ->label('Fecha fin')
                    ->date()
                    ->sortable()
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'retirados'),
                // Ordenable: ascendente = habilitados primero; descendente =
                // deshabilitados primero (dentro de cada grupo, mayor N° primero).
                TextColumn::make('estado')
                    ->badge()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw('estado = ? '.($direction === 'asc' ? 'desc' : 'asc'), [EstadoPersonal::Habilitado->value])
                        ->orderBy('id', 'desc')),
            ])
            // Habilitados primero, después deshabilitados; dentro de cada
            // grupo, de mayor a menor N° de item.
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('estado = ? desc', [EstadoPersonal::Habilitado->value])
                ->orderBy('id', 'desc'))
            ->filters([
                SelectFilter::make('estado')->options(EstadoPersonal::class),
                SelectFilter::make('especialidades')
                    ->label('Especialidad')
                    ->options(PersonalEspecialidades::flat())
                    ->query(fn ($query, $data) => $data['value']
                        ? $query->whereJsonContains('especialidades', $data['value'])
                        : $query),
                ...FiltrosContacto::make(),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                // También en Retirados: abre la ficha de solo lectura con el
                // recuadro de la baja (ver PersonalForm).
                EditAction::make()->label('Ver')->visible(fn () => auth()->user()?->puede('editar')),
                // Habilitar/Deshabilitar como botón directo (no escondido en
                // el menú "..."), en Vigentes (habilitados y deshabilitados).
                // Tras deshabilitar/habilitar o retirar, vuelve a Vigentes.
                PersonalActions::toggleEstado()
                    ->visible(fn (Personal $record) => ! $record->trashed())
                    ->after(fn ($livewire) => $livewire->redirect(PersonalResource::getUrl('index', ['tab' => 'vigentes']))),
                PersonalActions::restaurarYHabilitar()
                    ->visible(fn (Personal $record) => $record->trashed() && auth()->user()?->puede('editar')),
                ActionGroup::make([
                    PersonalActions::asignarUsuario(),
                ])->visible(fn (Personal $record) => ! $record->trashed()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Retirar seleccionados')
                        ->modalHeading('¿Seguro que desea retirar a:')
                        ->modalSubmitActionLabel('Guardar')
                        // Guardar a la izquierda y Cancelar a la derecha, igual
                        // que en la ficha (ver .netley-eliminar-personal).
                        ->modalFooterActionsAlignment(Alignment::Start)
                        ->extraModalWindowAttributes(['class' => 'netley-eliminar-personal'])
                        ->successNotificationTitle('Personal retirado')
                        ->modalDescription(fn (Collection $records): HtmlString => new HtmlString(
                            '<strong style="font-size:1rem;">'.$records->map(fn (Personal $p) => e($p->nombre_completo))->join(', ').'</strong>?'
                        ))
                        ->visible(fn () => auth()->user()?->puede('eliminar'))
                        ->after(fn ($livewire) => $livewire->redirect(PersonalResource::getUrl('index', ['tab' => 'vigentes']))),
                ]),
            ]);
    }
}
