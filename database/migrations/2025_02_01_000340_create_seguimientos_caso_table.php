<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimientos_caso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->dateTime('fecha_seguimiento');
            $table->string('responsable', 100)->nullable();
            $table->string('etapa_proceso', 50)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimientos_caso');
    }
};
