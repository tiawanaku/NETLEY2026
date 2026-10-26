<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llamadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->nullable()->constrained('personal')->nullOnDelete();
            $table->string('personal_texto', 200)->nullable(); // respaldo del texto libre legacy cuando no hubo match exacto
            $table->foreignId('consulta_id')->nullable()->constrained('consultas')->nullOnDelete();
            $table->string('nombres', 100);
            $table->string('ap_paterno', 100)->nullable();
            $table->string('ap_materno', 100)->nullable();
            $table->text('motivo')->nullable();
            $table->text('observaciones')->nullable();
            $table->text('observaciones_netley')->nullable();
            $table->string('creado_por', 100)->nullable();
            $table->string('numero_consulta', 80)->nullable();
            $table->string('ciudad', 120)->nullable();
            $table->string('materia_legal', 20)->nullable(); // App\Enums\Especialidad
            $table->foreignId('delito_id')->nullable()->constrained('delitos')->nullOnDelete();
            $table->string('delito_texto', 255)->nullable();
            $table->decimal('iguala', 12, 2)->default(0);
            $table->decimal('anticipo', 12, 2)->default(0);
            $table->decimal('comision_bs', 12, 2)->default(0);
            $table->decimal('comision_pct', 12, 2)->default(0);
            $table->string('accion', 100)->nullable();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->time('duracion')->nullable();
            $table->unsignedInteger('intentos')->default(0);
            $table->string('origen', 255)->nullable();
            $table->timestamps();

            $table->index('fecha');
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llamadas');
    }
};
