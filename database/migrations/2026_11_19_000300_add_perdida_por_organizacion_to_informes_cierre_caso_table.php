<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre con pérdida: monto de la pérdida y responsable por organización
 * (Hand i Hand = patrocinio, Netley = saldo pendiente), ver CierreCasoSchema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('informes_cierre_caso', function (Blueprint $table) {
            $table->decimal('perdida_hih', 12, 2)->nullable()->after('perdida');
            $table->string('responsable_hih', 255)->nullable()->after('perdida_hih');
            $table->decimal('perdida_netley', 12, 2)->nullable()->after('responsable_hih');
            $table->string('responsable_netley', 255)->nullable()->after('perdida_netley');
        });
    }

    public function down(): void
    {
        Schema::table('informes_cierre_caso', function (Blueprint $table) {
            $table->dropColumn(['perdida_hih', 'responsable_hih', 'perdida_netley', 'responsable_netley']);
        });
    }
};
