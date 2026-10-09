<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - modalidad_pago: "total" (el cliente paga todo de una vez) o "plan"
     *   (anticipo opcional + cuotas mensuales).
     * - Patrocinio Hand in Hand (HIH): cubre porcentaje_patrocinio de la
     *   iguala (monto_patrocinio); el cliente paga el resto. El saldo del caso
     *   sigue siendo la iguala completa: la parte de HIH se registra cuando
     *   llega, como pago "Pago delegado (HIH)".
     */
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->string('modalidad_pago', 10)->default('plan')->after('pagado');
            $table->boolean('patrocinio_hih')->default(false)->after('modalidad_pago');
            $table->decimal('porcentaje_patrocinio', 5, 2)->nullable()->after('patrocinio_hih');
            $table->decimal('monto_patrocinio', 12, 2)->default(0)->after('porcentaje_patrocinio');
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn(['modalidad_pago', 'patrocinio_hih', 'porcentaje_patrocinio', 'monto_patrocinio']);
        });
    }
};
