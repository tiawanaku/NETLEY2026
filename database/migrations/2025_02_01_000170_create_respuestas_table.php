<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id')->constrained('consultas')->cascadeOnDelete();
            $table->foreignId('personal_id')->nullable()->constrained('personal')->nullOnDelete();
            $table->text('respuesta');
            $table->string('paso', 20)->default('respondido');
            $table->string('designacion', 20)->nullable(); // App\Enums\Especialidad
            $table->foreignId('delito_id')->nullable()->constrained('delitos')->nullOnDelete();
            $table->string('delito_texto', 255)->nullable();
            $table->boolean('publicado')->default(false);
            $table->dateTime('fecha_respuesta');
            $table->text('nota')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas');
    }
};
