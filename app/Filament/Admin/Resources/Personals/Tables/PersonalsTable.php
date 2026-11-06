<?php

namespace App\Filament\Admin\Resources\Personals\Tables;

use App\Enums\EstadoPersonal;
use App\Filament\Admin\Resources\Personals\Actions\PersonalActions;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
                TextColumn::make('estado')
                    ->badge(),
            ])
            ->defaultSort('id', 'desc')
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
                EditAction::make()->label('Ver')->visible(fn (Personal $record) => ! $record->trashed() && auth()->user()?->puede('editar')),
                // Habilitar/Deshabilitar como botón directo (no escondido en
                // el menú "..."), tanto en Vigentes como en Retirados.
                PersonalActions::toggleEstado()->visible(fn (Personal $record) => ! $record->trashed()),
                PersonalActions::restaurarYHabilitar()
                    ->visible(fn (Personal $record) => $record->trashed() && auth()->user()?->puede('editar')),
                ActionGroup::make([
                    PersonalActions::asignarUsuario(),
                ])->visible(fn (Personal $record) => ! $record->trashed()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('¿Seguro que desea eliminar a:')
                        ->modalDescription(fn (Collection $records): HtmlString => new HtmlString(
                            '<strong style="font-size:1rem;">'.$records->map(fn (Personal $p) => e($p->nombre_completo))->join(', ').'</strong>?'
                        ))
                        ->visible(fn () => auth()->user()?->puede('eliminar')),
                ]),
            ]);
    }
}
