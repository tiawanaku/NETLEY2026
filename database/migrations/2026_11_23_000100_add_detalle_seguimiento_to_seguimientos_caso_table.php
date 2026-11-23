<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento del proceso más ordenado: abogado (personal) que reporta,
 * instancia donde ocurrió, tipo de actuación y la próxima actuación
 * pendiente (fecha + qué hay que hacer), para saber de un vistazo en qué
 * etapa está el caso y qué sigue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimientos_caso', function (Blueprint $table) {
            $table->foreignId('personal_id')->nullable()->after('caso_id')->constrained('personal')->nullOnDelete();
            $table->string('etapa_proceso', 100)->nullable()->change();
            $table->string('instancia', 150)->nullable()->after('etapa_proceso');
            $table->string('tipo_actuacion', 60)->nullable()->after('instancia');
            $table->date('proxima_fecha')->nullable()->after('observaciones');
            $table->string('proxima_actuacion', 255)->nullable()->after('proxima_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('seguimientos_caso', function (Blueprint $table) {
            $table->dropConstrainedForeignId('personal_id');
            $table->dropColumn(['instancia', 'tipo_actuacion', 'proxima_fecha', 'proxima_actuacion']);
        });
    }
};
