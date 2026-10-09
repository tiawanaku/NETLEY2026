<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('informes_cita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->unique()->constrained('citas')->cascadeOnDelete();
            $table->date('fecha_informe');
            $table->text('detalle')->nullable();
            $table->string('forma_ingreso', 20)->nullable();
            $table->string('nombre_colegio', 100)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('nombres', 60)->nullable();
            $table->string('apellidos', 60)->nullable();
            $table->string('responsable', 60)->nullable();
            $table->string('acciones', 60)->nullable();
            $table->decimal('anticipo', 12, 2)->default(0);
            $table->decimal('iguala', 12, 2)->default(0);
            $table->string('comision_bs', 100)->nullable();
            $table->string('comision_pct', 100)->nullable();
            $table->text('redaccion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informes_cita');
    }
};
