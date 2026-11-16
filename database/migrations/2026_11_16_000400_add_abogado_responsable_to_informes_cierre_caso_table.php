<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formulario de cierre del caso: "Abogado responsable" (nombre del abogado,
 * elegido de la lista; ver CierreCasoSchema).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('informes_cierre_caso', function (Blueprint $table) {
            $table->string('abogado_responsable', 255)->nullable()->after('resultado');
        });
    }

    public function down(): void
    {
        Schema::table('informes_cierre_caso', function (Blueprint $table) {
            $table->dropColumn('abogado_responsable');
        });
    }
};
