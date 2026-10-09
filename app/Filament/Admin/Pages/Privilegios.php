<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Rol;
use App\Support\Permisos;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Matriz de privilegios por rol: muestra qué secciones puede ver cada rol y
 * permite otorgarlas o quitarlas con un clic. Master y Administrador no
 * aparecen como columnas editables porque siempre tienen acceso completo
 * (ver User::puede()) — esta página solo restringe/habilita a Finanzas,
 * Abogado, Secretaria y Pasante.
 */
class Privilegios extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Privilegios';

    protected static \UnitEnum|string|null $navigationGroup = 'Personal';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'privilegios';

    protected string $view = 'filament.admin.pages.privilegios';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getTitle(): string
    {
        return 'Privilegios';
    }

    /**
     * @return array<int, Rol>
     */
    public function getRolesConfigurablesProperty(): array
    {
        return Permisos::rolesConfigurables();
    }

    /**
     * @return array<string, string>
     */
    public function getClavesProperty(): array
    {
        return Permisos::claves();
    }

    public function tienePermiso(Rol $rol, string $clave): bool
    {
        return Permisos::tiene($rol, $clave);
    }

    public function alternar(int $rolValue, string $clave): void
    {
        $rol = Rol::from($rolValue);

        if (! auth()->user()?->isAdmin()) {
            abort(403);
        }

        $concedido = Permisos::alternar($rol, $clave);

        Notification::make()
            ->title($concedido
                ? $rol->getLabel().' ahora tiene acceso a '.Permisos::claves()[$clave]
                : 'Se quitó a '.$rol->getLabel().' el acceso a '.Permisos::claves()[$clave])
            ->success()
            ->send();
    }
}
