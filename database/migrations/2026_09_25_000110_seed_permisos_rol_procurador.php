<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Le da a Procurador los mismos permisos por defecto que Pasante/Secretaria (ver 2026_09_24_000100). */
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

        foreach ($abiertas as $clave) {
            $filas[] = ['rol' => Rol::Procurador->value, 'clave' => $clave, 'created_at' => now(), 'updated_at' => now()];
        }

        DB::table('permisos_rol')->insert($filas);
    }

    public function down(): void
    {
        DB::table('permisos_rol')->where('rol', Rol::Procurador->value)->delete();
    }
};
