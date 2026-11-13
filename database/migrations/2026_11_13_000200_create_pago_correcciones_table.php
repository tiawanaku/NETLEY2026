<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de correcciones de pagos ("Editar pago" en el Plan de pagos):
 * cada edición exige una justificación y deja anotado qué cambió (antes /
 * después), quién y cuándo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_correcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('caso_id')->nullable()->constrained('casos')->nullOnDelete();
            $table->text('justificacion');
            $table->json('antes');
            $table->json('despues');
            $table->string('corregido_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_correcciones');
    }
};
