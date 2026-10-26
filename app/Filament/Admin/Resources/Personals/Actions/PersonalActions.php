<?php

namespace App\Filament\Admin\Resources\Personals\Actions;

use App\Enums\EstadoPersonal;
use App\Enums\Rol;
use App\Models\Personal;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;

class PersonalActions
{
    public static function toggleEstado(): Action
    {
        return Action::make('toggleEstado')
            ->label(fn (Personal $record) => $record->estado === EstadoPersonal::Habilitado ? 'Deshabilitar' : 'Habilitar')
            ->icon(fn (Personal $record) => $record->estado === EstadoPersonal::Habilitado ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
            ->color(fn (Personal $record) => $record->estado === EstadoPersonal::Habilitado ? 'danger' : 'success')
            ->requiresConfirmation()
            ->schema(fn (Personal $record) => $record->estado === EstadoPersonal::Habilitado
                ? [TextInput::make('motivo_baja')->label('Motivo')->required()]
                : [])
            ->action(function (Personal $record, array $data): void {
                if ($record->estado === EstadoPersonal::Habilitado) {
                    $record->update([
                        'estado' => EstadoPersonal::Inhabilitado,
                        'fecha_fin' => now()->toDateString(),
                        'motivo_baja' => $data['motivo_baja'] ?? null,
                    ]);
                } else {
                    $record->update(['estado' => EstadoPersonal::Habilitado, 'fecha_fin' => null]);
                }
            });
    }

    /**
     * Crea la cuenta de acceso de un personal que todavía no tiene una, o —
     * si ya la tiene — permite gestionarla: cambiar el usuario y/o
     * restablecer la contraseña (dejando la contraseña vacía la deja sin
     * cambios).
     */
    public static function asignarUsuario(): Action
    {
        return Action::make('asignarUsuario')
            ->label(fn (Personal $record) => $record->user ? 'Gestionar credenciales' : 'Asignar cuenta')
            ->icon('heroicon-o-key')
            ->color(fn (Personal $record) => $record->user ? 'gray' : 'primary')
            ->fillForm(fn (Personal $record) => [
                'username' => $record->user?->username,
            ])
            ->schema([
                TextInput::make('username')
                    ->label('Usuario')
                    ->required()
                    ->unique(table: 'users', column: 'username', ignorable: fn (Personal $record) => $record->user),
                TextInput::make('password')
                    ->label(fn (Personal $record) => $record->user ? 'Nueva contraseña' : 'Contraseña')
                    ->helperText(fn (Personal $record) => $record->user ? 'Déjala vacía para no cambiar la contraseña actual.' : null)
                    ->password()
                    ->revealable()
                    ->minLength(6)
                    ->required(fn (Personal $record) => ! $record->user),
            ])
            ->action(function (Personal $record, array $data): void {
                if ($record->user) {
                    $record->user->update([
                        'username' => $data['username'],
                        'name' => $data['username'],
                        ...(filled($data['password'] ?? null) ? ['password' => Hash::make($data['password'])] : []),
                    ]);

                    Notification::make()->title('Credenciales actualizadas')->success()->send();

                    return;
                }

                User::create([
                    'personal_id' => $record->id,
                    'name' => $data['username'],
                    'username' => $data['username'],
                    'email' => $data['username'].'@netley.local',
                    'password' => Hash::make($data['password']),
                ]);

                Notification::make()->title('Cuenta creada')->success()->send();
            });
    }

    /**
     * Solo para el rol Procuradora: elegir qué abogado(s) asiste. El acceso
     * a los casos de esos abogados queda habilitado automáticamente (ver
     * ScopesToAssignedAbogado), sin tener que asignarle cada caso aparte.
     */
    public static function asignarAbogados(): Action
    {
        return Action::make('asignarAbogados')
            ->label('Asignar abogados')
            ->icon('heroicon-o-user-group')
            ->color('gray')
            ->visible(fn (Personal $record) => $record->rol === Rol::Procurador)
            ->fillForm(fn (Personal $record) => [
                'abogados' => $record->abogadosAsignados()->pluck('personal.id')->all(),
            ])
            ->schema([
                Select::make('abogados')
                    ->label('Abogados que asiste')
                    ->options(fn () => Personal::query()
                        ->where('profesion', 'Abogado')
                        ->get()
                        ->mapWithKeys(fn (Personal $p) => [$p->id => $p->nombre_completo.' ('.$p->casos()->count().' casos)'])
                    )
                    ->multiple()
                    ->searchable()
                    ->helperText('Verá automáticamente los casos, citas y llamadas de los abogados que elijas aquí.'),
            ])
            ->action(function (Personal $record, array $data): void {
                $record->abogadosAsignados()->sync($data['abogados'] ?? []);

                Notification::make()->title('Abogados asignados actualizados')->success()->send();
            });
    }
}
