<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talleres', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_programada')->nullable();
            $table->time('hora_programada')->nullable();
            $table->string('nombres', 120)->nullable();
            $table->string('ap_paterno', 120)->nullable();
            $table->string('ap_materno', 120)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->text('motivo')->nullable();
            $table->string('numero_consulta', 80)->nullable();
            $table->string('materia_legal', 20)->nullable(); // App\Enums\Especialidad
            $table->foreignId('delito_id')->nullable()->constrained('delitos')->nullOnDelete();
            $table->string('delito_texto', 100)->nullable();
            $table->text('consulta')->nullable();
            $table->text('respuesta')->nullable();
            $table->string('creado_por', 50)->nullable();
            $table->string('personal_texto', 120)->nullable();
            $table->string('abogado_texto', 120)->nullable();
            $table->string('origen', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talleres');
    }
};
