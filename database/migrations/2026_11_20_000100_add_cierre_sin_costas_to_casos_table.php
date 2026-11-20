<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caso ganado con patrocinio HiH que se cierra SIN iniciar costas y costos
 * (botón "Cerrar caso" del asistente de costas): fecha y justificación. Con
 * esto el caso pasa de Pendiente a Cerrado, pero puede iniciar las costas
 * más adelante (ver Caso::actualizarEstadoCostas()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->date('costas_cierre_fecha')->nullable()->after('caso_origen_id');
            $table->text('costas_cierre_justificacion')->nullable()->after('costas_cierre_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn(['costas_cierre_fecha', 'costas_cierre_justificacion']);
        });
    }
};
