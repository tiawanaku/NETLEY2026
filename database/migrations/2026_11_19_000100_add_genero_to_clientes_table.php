<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Género del cliente (paso 1 del registro de Cliente ejecutivo y ficha del
 * cliente), con las mismas opciones que Personal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('genero', 20)->nullable()->after('fecha_nacimiento');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('genero');
        });
    }
};
