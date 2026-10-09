<?php

namespace App\Support;

use App\Enums\Rol;
use App\Models\PermisoRol;

/**
 * Catálogo de secciones del panel y consulta/edición de qué roles tienen
 * acceso a cada una. Master y Administrador siempre tienen acceso completo
 * (ver User::puede()) y no aparecen aquí como configurables.
 */
class Permisos
{
    /**
     * @return array<string, string> clave => etiqueta
     */
    public static function claves(): array
    {
        return [
            'personal' => 'Personal',
            'contactos' => 'Contactos',
            'consultas' => 'Consultas',
            'clientes' => 'Clientes',
            'casos' => 'Casos',
            'seguimiento_diario' => 'Seguimiento diario',
            'pagos' => 'Pagos',
            'agenda' => 'Agenda / Citas',
            'llamadas' => 'Llamadas',
            'talleres' => 'Talleres',
            'delitos' => 'Delitos (catálogo)',
            'testimonios' => 'Testimonios',
            'oficinas' => 'Oficinas (catálogo)',
            'municipios' => 'Municipios (catálogo)',
            'estadisticas' => 'Estadísticas',
            'sitio_web' => 'Contenido del sitio web',
            'editar' => 'Editar registros existentes',
            'eliminar' => 'Eliminar registros',
        ];
    }

    /**
     * Roles configurables desde la matriz de privilegios. Master y
     * Administrador quedan fuera: siempre tienen acceso total.
     *
     * @return array<int, Rol>
     */
    public static function rolesConfigurables(): array
    {
        return [Rol::Finanzas, Rol::Abogado, Rol::Secretaria, Rol::Pasante, Rol::Procurador];
    }

    public static function tiene(Rol $rol, string $clave): bool
    {
        return PermisoRol::query()->where('rol', $rol->value)->where('clave', $clave)->exists();
    }

    /**
     * @return array<string, bool> clave => concedido, para un rol dado
     */
    public static function paraRol(Rol $rol): array
    {
        $concedidas = PermisoRol::query()->where('rol', $rol->value)->pluck('clave')->all();

        return collect(self::claves())
            ->keys()
            ->mapWithKeys(fn (string $clave) => [$clave => in_array($clave, $concedidas, true)])
            ->all();
    }

    public static function alternar(Rol $rol, string $clave): bool
    {
        $existente = PermisoRol::query()->where('rol', $rol->value)->where('clave', $clave)->first();

        if ($existente) {
            $existente->delete();

            return false;
        }

        PermisoRol::create(['rol' => $rol->value, 'clave' => $clave]);

        return true;
    }
}
