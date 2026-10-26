<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactos', function (Blueprint $table) {
            $table->id();
            $table->string('institucion', 100)->nullable();
            $table->string('entidad', 100)->nullable();
            $table->string('unidad', 100)->nullable();
            $table->string('cargo', 200)->nullable();
            $table->string('profesion', 200)->nullable();
            $table->string('nombre', 50);
            $table->string('apellido', 50)->nullable();
            $table->string('direccion', 500)->nullable();
            $table->string('zona', 500)->nullable();
            $table->string('ciudad', 200)->nullable();
            $table->string('correo', 200)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('horario_contacto', 1000)->nullable();
            $table->text('nota')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};
