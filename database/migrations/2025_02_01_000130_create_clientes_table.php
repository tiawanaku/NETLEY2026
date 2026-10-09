<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombres', 60);
            $table->string('ap_paterno', 30);
            $table->string('ap_materno', 30)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('correo', 80)->nullable();
            $table->string('ci', 20)->unique();
            $table->string('extension', 20)->nullable();
            $table->string('sucursal', 60)->default('LA PAZ');
            $table->string('direccion', 300)->nullable();
            $table->unsignedInteger('nro_casos')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
