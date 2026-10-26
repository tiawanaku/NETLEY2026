<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->decimal('nuevo_saldo', 12, 2)->nullable();
            $table->string('estado', 20)->default('pendiente'); // App\Enums\EstadoCuota
            $table->string('creado_por', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_pago');
    }
};
