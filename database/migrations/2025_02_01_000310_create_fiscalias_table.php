<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscalias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('vigente')->default(true);
            $table->string('num_caso', 100)->nullable();
            $table->string('fiscalia_num', 100)->nullable();
            $table->string('nombre_fiscal', 150)->nullable();
            $table->string('telefono_fiscal', 30)->nullable();
            $table->string('investigador', 150)->nullable();
            $table->string('telefono_investigador', 30)->nullable();
            $table->string('auxiliar', 150)->nullable();
            $table->string('telefono_auxiliar', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscalias');
    }
};
