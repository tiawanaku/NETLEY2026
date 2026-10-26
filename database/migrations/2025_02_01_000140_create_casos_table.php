<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('especialidad', 20); // App\Enums\Especialidad
            $table->foreignId('delito_id')->nullable()->constrained('delitos')->nullOnDelete();
            $table->string('delito_texto', 150)->nullable(); // texto libre legacy cuando no hubo match con el catálogo
            $table->text('descripcion')->nullable();
            $table->string('apersonamiento', 60)->nullable();
            $table->decimal('iguala', 12, 2)->default(0);
            $table->decimal('saldo', 12, 2)->default(0);
            $table->decimal('pagado', 12, 2)->default(0);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 30)->default('activo_pendiente'); // App\Enums\EstadoCaso
            $table->string('ciudad', 60)->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index('especialidad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casos');
    }
};
