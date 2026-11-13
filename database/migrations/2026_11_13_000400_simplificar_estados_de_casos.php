<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estados del caso: solo Activo, Cerrado y Eliminado (ver App\Enums\EstadoCaso).
 * "Activo - Pendiente" y "Reactivado - Pendiente" pasan a "Activo"; los casos
 * eliminados (borrado lógico) quedan con estado "Eliminado".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('casos')->whereIn('estado', ['activo_pendiente', 'reactivado_pendiente'])->update(['estado' => 'activo']);
        DB::table('casos')->whereNotNull('deleted_at')->update(['estado' => 'eliminado']);

        Schema::table('casos', function ($table) {
            $table->string('estado', 30)->default('activo')->change();
        });
    }

    public function down(): void
    {
        DB::table('casos')->whereIn('estado', ['activo', 'eliminado'])->update(['estado' => 'activo_pendiente']);

        Schema::table('casos', function ($table) {
            $table->string('estado', 30)->default('activo_pendiente')->change();
        });
    }
};
