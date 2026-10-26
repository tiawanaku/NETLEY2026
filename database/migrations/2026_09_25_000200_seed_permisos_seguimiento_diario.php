<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Habilita "Seguimiento diario" por defecto para Abogado y Procurador (quienes tienen casos asignados). */
return new class extends Migration
{
    public function up(): void
    {
        $filas = [];

        foreach ([Rol::Abogado, Rol::Procurador] as $rol) {
            $filas[] = ['rol' => $rol->value, 'clave' => 'seguimiento_diario', 'created_at' => now(), 'updated_at' => now()];
        }

        DB::table('permisos_rol')->insert($filas);
    }

    public function down(): void
    {
        DB::table('permisos_rol')->where('clave', 'seguimiento_diario')->delete();
    }
};
