<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimientos_agendados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            // Instancia polimórfica: Fiscalia, Juzgado u OtroSeguimiento (reemplaza el
            // discriminador de texto "tipo_instancia" del sistema legacy).
            $table->morphs('instancia');
            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->text('detalles')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimientos_agendados');
    }
};
