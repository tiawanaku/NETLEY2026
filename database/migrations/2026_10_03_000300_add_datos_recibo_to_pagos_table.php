<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->string('tipo_pago', 30)->default('a_cuenta')->after('monto');
            $table->string('forma_pago', 20)->default('efectivo')->after('tipo_pago');
            $table->string('nro_cheque', 50)->nullable()->after('forma_pago');
            $table->string('banco', 100)->nullable()->after('nro_cheque');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn(['tipo_pago', 'forma_pago', 'nro_cheque', 'banco']);
        });
    }
};
