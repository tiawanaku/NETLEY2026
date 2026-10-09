<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('informes_cierre_caso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('resultado', 128)->nullable();
            $table->decimal('saldo', 12, 2)->default(0);
            $table->decimal('perdida', 12, 2)->default(0);
            $table->string('asume', 255)->nullable();
            $table->string('seguimiento_responsable', 255)->nullable();
            $table->text('opciones')->nullable();
            $table->text('nota_netley')->nullable();
            $table->string('creado_por', 100)->nullable();
            $table->date('fecha_cierre')->nullable();
            $table->timestamps();

            $table->index('caso_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informes_cierre_caso');
    }
};
