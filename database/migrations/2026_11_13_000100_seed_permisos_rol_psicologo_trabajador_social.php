<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roles nuevos "Psicólogo" y "Trabajador social": arrancan con los mismos
 * permisos por defecto que Pasante/Secretaria (ver
 * 2026_09_25_000110_seed_permisos_rol_procurador); se ajustan después
 * desde Privilegios.
 */
return new class extends Migration
{
    public function up(): void
    {
        $abiertas = [
            'contactos', 'consultas', 'clientes', 'casos', 'pagos',
            'agenda', 'llamadas', 'talleres', 'delitos', 'testimonios',
            'oficinas', 'municipios',
        ];

        $filas = [];

        foreach ([Rol::Psicologo, Rol::TrabajadorSocial] as $rol) {
            foreach ($abiertas as $clave) {
                $existe = DB::table('permisos_rol')->where('rol', $rol->value)->where('clave', $clave)->exists();

                if (! $existe) {
                    $filas[] = ['rol' => $rol->value, 'clave' => $clave, 'created_at' => now(), 'updated_at' => now()];
                }
            }
        }

        DB::table('permisos_rol')->insert($filas);
    }

    public function down(): void
    {
        DB::table('permisos_rol')->whereIn('rol', [Rol::Psicologo->value, Rol::TrabajadorSocial->value])->delete();
    }
};
