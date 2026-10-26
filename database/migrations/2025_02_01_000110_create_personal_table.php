<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('rol')->default(5); // App\Enums\Rol
            $table->string('nombres', 60);
            $table->string('ap_paterno', 40);
            $table->string('ap_materno', 40)->nullable();
            $table->string('genero', 20)->nullable();
            $table->string('ci', 20)->unique();
            $table->string('ci_expedido', 10)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('nacionalidad', 30)->nullable();
            $table->string('direccion', 150)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('correo', 80)->nullable();
            $table->string('estado', 20)->default('habilitado'); // App\Enums\EstadoPersonal
            $table->string('cargo', 200)->nullable();
            $table->string('profesion', 200)->nullable();
            $table->json('especialidades')->nullable(); // lista de App\Enums\Especialidad
            $table->boolean('tiene_contrato')->default(true);
            $table->string('foto')->nullable();
            $table->string('ciudad_residencia', 60)->nullable();
            $table->string('estado_civil', 20)->nullable();
            $table->dateTime('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('motivo_baja', 500)->nullable();
            $table->text('nota')->nullable();
            $table->timestamps();

            $table->index('rol');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal');
    }
};
