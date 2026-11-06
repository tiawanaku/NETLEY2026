<?php

namespace App\Filament\Admin\Resources\Personals\Actions;

use App\Enums\EstadoPersonal;
use App\Enums\Rol;
use App\Models\Personal;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class PersonalActions
{
    /** Habilitado pero con una baja ya programada a futuro (ver action() abajo). */
    protected static function tieneBajaProgramada(Personal $record): bool
    {
        return $record->estado === EstadoPersonal::Habilitado
            && $record->fecha_fin !== null
            && $record->fecha_fin->gt(now()->startOfDay());
    }

    public static function toggleEstado(): Action
    {
        return Action::make('toggleEstado')
            ->label(function (Personal $record) {
                if (self::tieneBajaProgramada($record)) {
                    return 'Deshabilitación programada';
                }

                return $record->estado === EstadoPersonal::Habilitado ? 'Deshabilitar' : 'Habilitar';
            })
            ->icon(function (Personal $record) {
                if (self::tieneBajaProgramada($record)) {
                    return 'heroicon-o-clock';
                }

                return $record->estado === EstadoPersonal::Habilitado ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open';
            })
            ->color(function (Personal $record) {
                if (self::tieneBajaProgramada($record)) {
                    return 'warning';
                }

                return $record->estado === EstadoPersonal::Habilitado ? 'danger' : 'success';
            })
            ->requiresConfirmation()
            // Si la fecha de baja es hoy o anterior, se inhabilita de una
            // vez; si es a futuro, sigue Habilitado hasta esa fecha, cuando
            // el comando programado lo inhabilita solo (ver
            // App\Console\Commands\InhabilitarPersonalProgramado).
            ->schema(fn (Personal $record) => $record->estado === EstadoPersonal::Habilitado
                ? [
                    // Input nativo (no Flatpickr): se puede escribir la fecha con el
                    // teclado y participa en la cadena de Enter de enter-navigation.js,
                    // igual que el resto de los DatePicker del sistema.
                    DatePicker::make('fecha_fin')->label('Fecha de baja')->required()->default(now())->extraInputAttributes(['data-enter-nav' => 'true']),
                    TextInput::make('motivo_baja')->label('Motivo')->required()->extraInputAttributes(['data-enter-nav' => 'true']),
                ]
                : [])
            // Si ya hay una baja programada, reabre con esos mismos datos
            // para poder ajustarlos, en vez de reiniciar con la fecha de hoy.
            ->fillForm(fn (Personal $record): array => self::tieneBajaProgramada($record)
                ? ['fecha_fin' => $record->fecha_fin, 'motivo_baja' => $record->motivo_baja]
                : [])
            ->action(function (Personal $record, array $data): void {
                if ($record->estado === EstadoPersonal::Habilitado) {
                    $fechaFin = Carbon::parse($data['fecha_fin']);
                    $esInmediata = $fechaFin->lte(now()->startOfDay());

                    $record->update([
                        'estado' => $esInmediata ? EstadoPersonal::Inhabilitado : EstadoPersonal::Habilitado,
                        'fecha_fin' => $fechaFin->toDateString(),
                        'motivo_baja' => $data['motivo_baja'] ?? null,
                    ]);

                    Notification::make()
                        ->title($esInmediata
                            ? 'Personal inhabilitado'
                            : 'Baja programada para el '.$fechaFin->format('d/m/Y'))
                        ->success()
                        ->send();
                } else {
                    $record->update(['estado' => EstadoPersonal::Habilitado, 'fecha_fin' => null, 'motivo_baja' => null]);
                }
            });
    }

    /**
     * Para la pestaña "Eliminados": restaura el registro (deshace el borrado
     * lógico) y de una lo deja Habilitado, en vez de dejarlo Inhabilitado
     * (como queda al eliminarlo, ver Personal::booted()) y obligar a un
     * segundo clic en "Habilitar" aparte.
     */
    public static function restaurarYHabilitar(): RestoreAction
    {
        return RestoreAction::make()
            ->label('Habilitar')
            ->icon('heroicon-o-lock-open')
            ->color('success')
            ->after(fn (Personal $record) => $record->update([
                'estado' => EstadoPersonal::Habilitado,
                'fecha_fin' => null,
                'motivo_baja' => null,
            ]));
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
