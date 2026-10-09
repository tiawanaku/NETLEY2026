<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imagenes_carrusel', function (Blueprint $table) {
            $table->id();
            $table->string('imagen');
            $table->string('titulo', 150)->nullable();
            $table->string('subtitulo', 250)->nullable();
            $table->string('enlace')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagenes_carrusel');
    }
};
