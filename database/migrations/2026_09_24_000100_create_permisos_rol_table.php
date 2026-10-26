<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permisos por rol: la existencia de una fila (rol, clave) habilita esa
 * sección para ese rol. Master y Administrador no pasan por esta tabla (ver
 * User::puede()): siempre tienen acceso completo, igual que hoy.
 *
 * Los valores por defecto preservan el comportamiento actual del sistema:
 * hoy casi todos los recursos son accesibles para cualquier usuario
 * autenticado y habilitado (sin canAccess()), excepto Personal y
 * Estadísticas. Por eso se otorgan todas las claves a Finanzas/Abogado/
 * Secretaria/Pasante salvo 'personal' y 'eliminar' (que ya estaban
 * restringidas a Master/Admin en toda la app) y 'estadisticas' (restringida
 * a Master/Admin/Finanzas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos_rol', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('rol');
            $table->string('clave');
            $table->timestamps();

            $table->unique(['rol', 'clave']);
        });

        $abiertas = [
            'contactos', 'consultas', 'clientes', 'casos', 'pagos',
            'agenda', 'llamadas', 'talleres', 'delitos', 'testimonios',
            'oficinas', 'municipios',
        ];

        $roles = [Rol::Finanzas, Rol::Abogado, Rol::Secretaria, Rol::Pasante];

        $filas = [];

        foreach ($roles as $rol) {
            foreach ($abiertas as $clave) {
                $filas[] = ['rol' => $rol->value, 'clave' => $clave, 'created_at' => now(), 'updated_at' => now()];
            }
        }

        $filas[] = ['rol' => Rol::Finanzas->value, 'clave' => 'estadisticas', 'created_at' => now(), 'updated_at' => now()];

        DB::table('permisos_rol')->insert($filas);
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_rol');
    }
};
