<?php

namespace App\Filament\Admin\Resources\Personals\Tables;

use App\Enums\EstadoPersonal;
use App\Enums\Rol;
use App\Filament\Admin\Resources\Personals\Actions\PersonalActions;
use App\Support\PersonalEspecialidades;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
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
                    ->label('ID Cliente')
                    ->sortable(),
                ImageColumn::make('foto')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->nombres.' '.$record->ap_paterno)),
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->searchable(['nombres', 'ap_paterno', 'ap_materno'])
                    ->sortable(['nombres']),
                TextColumn::make('ci')
                    ->label('CI')
                    ->searchable(),
                TextColumn::make('cargo')
                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Str::limit(is_array($state) ? implode(', ', $state) : (string) $state, 25))
                    ->searchable()
                    ->tooltip(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                TextColumn::make('rol')
                    ->badge()
                    ->sortable(),
                TextColumn::make('especialidades')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PersonalEspecialidades::label($state))
                    ->toggleable(),
                TextColumn::make('estado')
                    ->badge(),
                TextColumn::make('telefono')
                    ->toggleable(),
                TextColumn::make('correo')
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
                SelectFilter::make('rol')->options(Rol::class),
                SelectFilter::make('estado')->options(EstadoPersonal::class),
                SelectFilter::make('especialidades')
                    ->label('Especialidad')
                    ->options(PersonalEspecialidades::grouped())
                    ->query(fn ($query, $data) => $data['value']
                        ? $query->whereJsonContains('especialidades', $data['value'])
                        : $query),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                EditAction::make()->visible(fn () => auth()->user()?->puede('editar')),
                ActionGroup::make([
                    PersonalActions::toggleEstado(),
                    PersonalActions::asignarUsuario(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
                ]),
            ]);
    }
}
