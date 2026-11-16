<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Iniciar costas y costos": el proceso nuevo queda ligado al caso ganado
 * (con patrocinio HiH) que lo originó; así ese caso deja de pedirlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->foreignId('caso_origen_id')->nullable()->after('cliente_id')
                ->constrained('casos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caso_origen_id');
        });
    }
};
