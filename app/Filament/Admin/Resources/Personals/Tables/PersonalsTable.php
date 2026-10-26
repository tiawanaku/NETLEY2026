<?php

namespace App\Filament\Admin\Resources\Personals\Tables;

use App\Enums\EstadoPersonal;
use App\Enums\RedSocialPlataforma;
use App\Filament\Admin\Resources\Personals\Actions\PersonalActions;
use App\Filament\Admin\Support\FiltrosContacto;
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

class PersonalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Item personal')
                    ->sortable(),
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->searchable(['nombres', 'ap_paterno', 'ap_materno'])
                    ->sortable(['nombres']),
                TextColumn::make('ci')
                    ->label('CI')
                    ->searchable(),
                TextColumn::make('cargo')
                    ->label('Profesión')
                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Str::limit(is_array($state) ? implode(', ', $state) : (string) $state, 25))
                    ->searchable()
                    ->tooltip(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                TextColumn::make('especialidades')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PersonalEspecialidades::label($state))
                    ->toggleable(),
                TextColumn::make('estado')
                    ->badge(),
                TextColumn::make('fecha_inicio')
                    ->label('Fecha inicio')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('fecha_fin')
                    ->label('Fecha de baja')
                    ->date()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('redes_sociales')
                    ->label('Redes sociales')
                    ->state(fn ($record) => collect($record->redes_sociales ?? [])
                        ->pluck('plataforma')
                        ->map(fn (?string $plataforma) => RedSocialPlataforma::tryFrom((string) $plataforma)?->getLabel() ?? $plataforma)
                        ->all())
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('user.username')
                    ->label('Usuario')
                    ->placeholder('sin cuenta')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                ActionGroup::make([
                    PersonalActions::toggleEstado(),
                    PersonalActions::asignarUsuario(),
                ])->visible(fn (Personal $record) => ! $record->trashed()),
                RestoreAction::make()
                    ->visible(fn (Personal $record) => $record->trashed() && auth()->user()?->puede('editar')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
                ]),
            ]);
    }
}
