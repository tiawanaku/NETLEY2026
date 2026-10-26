<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('caso_id')->nullable()->constrained('casos')->nullOnDelete();
            $table->string('ruta', 255);
            $table->string('descripcion', 100)->nullable();
            $table->dateTime('fecha_origen');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_cliente');
    }
};
