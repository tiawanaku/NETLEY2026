<?php

namespace App\Filament\Admin\Resources\Clientes\Actions;

use App\Models\Cliente;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;

class ClienteActions
{
    /**
     * Crea o gestiona el acceso del cliente al portal (resources/views/portal):
     * el propio registro de Cliente es Authenticatable para el guard `cliente`,
     * no depende de la tabla `users` (esa es solo para el personal).
     */
    public static function gestionarAcceso(): Action
    {
        return Action::make('gestionarAcceso')
            ->label(fn (Cliente $record) => $record->username ? 'Gestionar acceso al portal' : 'Dar acceso al portal')
            ->icon('heroicon-o-key')
            ->color(fn (Cliente $record) => $record->username ? 'gray' : 'primary')
            ->fillForm(fn (Cliente $record) => [
                'username' => $record->username,
            ])
            ->schema([
                TextInput::make('username')
                    ->label('Usuario')
                    ->required()
                    ->unique(table: 'clientes', column: 'username', ignorable: fn (Cliente $record) => $record),
                TextInput::make('password')
                    ->label(fn (Cliente $record) => $record->username ? 'Nueva contraseña' : 'Contraseña')
                    ->helperText(fn (Cliente $record) => $record->username ? 'Déjala vacía para no cambiar la contraseña actual.' : null)
                    ->password()
                    ->revealable()
                    ->minLength(6)
                    ->required(fn (Cliente $record) => ! $record->username),
            ])
            ->action(function (Cliente $record, array $data): void {
                $esNuevo = ! $record->username;

                $record->update([
                    'username' => $data['username'],
                    ...(filled($data['password'] ?? null) ? ['password' => Hash::make($data['password'])] : []),
                ]);

                Notification::make()
                    ->title($esNuevo ? 'Acceso al portal creado' : 'Acceso al portal actualizado')
                    ->success()
                    ->send();
            });
    }
}
