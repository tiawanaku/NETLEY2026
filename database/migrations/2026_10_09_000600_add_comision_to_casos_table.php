<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comisión por un pago extra del caso (ej. un referido): dos campos
     * independientes que se llenan a mano, sin relación entre sí ni con el
     * resto del plan de pagos — solo se guardan tal cual se cargan.
     */
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->decimal('comision_porcentaje', 5, 2)->nullable()->after('monto_patrocinio');
            $table->decimal('comision_monto', 12, 2)->nullable()->after('comision_porcentaje');
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn(['comision_porcentaje', 'comision_monto']);
        });
    }
};
