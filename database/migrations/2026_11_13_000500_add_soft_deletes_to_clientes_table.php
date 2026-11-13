<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eliminar un cliente ejecutivo ya no lo borra de la base (lo que antes
 * borraba en cascada, para siempre, sus casos, pagos y documentos): queda
 * marcado como eliminado y sus casos pasan a "Casos eliminados".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
