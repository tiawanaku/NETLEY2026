<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos del formulario de papel "Consultas directas" que todavía no
     * existían en el sistema: comisión del abogado por cita (iguala,
     * anticipo, % y monto de comisión) y si la cita quedó confirmada, más
     * quién atendió la consulta al registrarla.
     */
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->decimal('bs_iguala', 10, 2)->nullable();
            $table->decimal('bs_anticipo', 10, 2)->nullable();
            $table->decimal('porcentaje_comision', 5, 2)->nullable();
            $table->decimal('bs_comision', 10, 2)->nullable();
            $table->boolean('confirmada')->nullable();
        });

        Schema::table('consultas', function (Blueprint $table) {
            $table->string('atendido_por', 100)->nullable()->after('forma_ingreso');
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropColumn(['bs_iguala', 'bs_anticipo', 'porcentaje_comision', 'bs_comision', 'confirmada']);
        });

        Schema::table('consultas', function (Blueprint $table) {
            $table->dropColumn('atendido_por');
        });
    }
};
