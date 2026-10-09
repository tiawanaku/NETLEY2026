<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->date('fecha_pago');
            $table->unsignedInteger('nro_cuota');
            $table->string('sucursal', 30)->default('LA PAZ');
            $table->unsignedInteger('nro_recibo')->unique();
            $table->string('registrado_por', 60)->nullable();
            $table->timestamps();

            $table->index('fecha_pago');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
