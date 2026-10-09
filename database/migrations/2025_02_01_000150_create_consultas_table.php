<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->nullable()->constrained('casos')->nullOnDelete();
            $table->string('nombres', 40);
            $table->string('ap_paterno', 30)->nullable();
            $table->string('ap_materno', 30)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('correo', 80)->nullable();
            $table->text('consulta');
            $table->text('nota_interna')->nullable();
            $table->dateTime('fecha_consulta');
            $table->string('pais', 40)->nullable();
            $table->string('ciudad', 40)->nullable();
            $table->string('provincia', 40)->nullable();
            $table->string('direccion', 500)->nullable();
            $table->string('estado', 30)->default('pendiente'); // App\Enums\EstadoConsulta
            $table->date('fecha_contacto')->nullable();
            $table->string('origen', 100)->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index('fecha_consulta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas');
    }
};
