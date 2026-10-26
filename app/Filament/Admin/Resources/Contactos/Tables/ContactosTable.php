<?php

namespace App\Filament\Admin\Resources\Contactos\Tables;

use App\Filament\Admin\Support\FiltrosContacto;
use App\Support\Telefono;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->searchable(['nombre', 'apellido']),
                TextColumn::make('cargo')->searchable(),
                TextColumn::make('institucion')->searchable(),
                TextColumn::make('entidad')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ciudad')->searchable(),
                TextColumn::make('telefono')->label('Teléfono')->searchable()->formatStateUsing(fn (?string $state): ?string => Telefono::formatear($state)),
                TextColumn::make('correo')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('personal.nombres')->label('Personal vinculado')->badge()->limitList(2),
            ])
            // Contactos no tiene WhatsApp.
            ->filters(FiltrosContacto::make(['telefono', 'correo']))
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
