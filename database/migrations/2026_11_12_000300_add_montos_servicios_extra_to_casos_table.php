<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Pago de servicios extra" (paso 3 de Cliente ejecutivo): monto de cada
 * servicio requerido (Psicología, Trabajo social, Otro). Solo se guarda;
 * no entra en la iguala, el saldo ni el plan de cuotas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->decimal('monto_servicio_psicologia', 12, 2)->nullable()->after('servicio_otro_detalle');
            $table->decimal('monto_servicio_trabajo_social', 12, 2)->nullable()->after('monto_servicio_psicologia');
            $table->decimal('monto_servicio_otro', 12, 2)->nullable()->after('monto_servicio_trabajo_social');
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn(['monto_servicio_psicologia', 'monto_servicio_trabajo_social', 'monto_servicio_otro']);
        });
    }
};
