<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otros_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->string('nombre_instancia', 255)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('num_caso', 100)->nullable();
            $table->string('nombre_contacto', 255)->nullable();
            $table->string('numero_contacto', 100)->nullable();
            $table->text('detalles')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otros_seguimientos');
    }
};
