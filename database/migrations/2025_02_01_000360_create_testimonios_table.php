<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->longText('testimonio');
            $table->unsignedTinyInteger('calificacion')->default(5);
            $table->string('correo', 100)->nullable();
            $table->dateTime('fecha')->useCurrent();
            $table->string('estado', 20)->default('pendiente'); // App\Enums\EstadoAprobacion
            $table->boolean('visible')->default(false);
            $table->longText('notas_admin')->nullable();
            $table->dateTime('fecha_modificacion')->nullable();
            $table->string('modificado_por', 100)->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index('visible');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonios');
    }
};
