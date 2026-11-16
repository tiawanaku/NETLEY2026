<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Detalles del proceso" en la ficha del caso (Casos > Datos del proceso):
 * qué código identifica al caso (NUREJ, CUD u otro escrito a mano) y su
 * detalle (el número, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->string('caso_tipo', 20)->nullable()->after('descripcion');
            $table->string('caso_tipo_otro', 100)->nullable()->after('caso_tipo');
            $table->string('caso_detalle', 255)->nullable()->after('caso_tipo_otro');
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn(['caso_tipo', 'caso_tipo_otro', 'caso_detalle']);
        });
    }
};
