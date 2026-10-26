<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llamada_intentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('llamada_id')->constrained('llamadas')->cascadeOnDelete();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duracion_segundos')->nullable();
            $table->string('resultado', 100)->nullable();
            $table->text('notas')->nullable();
            $table->string('creado_por', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llamada_intentos');
    }
};
