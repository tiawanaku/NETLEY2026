<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id')->nullable()->constrained('consultas')->nullOnDelete();
            $table->foreignId('caso_id')->nullable()->constrained('casos')->nullOnDelete();
            $table->date('fecha');
            $table->time('hora');
            $table->text('detalle')->nullable();
            $table->string('tipo', 50)->nullable();
            $table->string('origen', 50)->nullable();
            $table->boolean('anulada')->default(false);
            $table->timestamps();

            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
