<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué abogado(s) asiste cada procuradora. El acceso de la procuradora a los
 * casos se calcula a partir de esta tabla (ver ScopesToAssignedAbogado):
 * ve los casos de los abogados que tiene asignados, sin necesidad de
 * asignarle cada caso uno por uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abogado_procurador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurador_id')->constrained('personal')->cascadeOnDelete();
            $table->foreignId('abogado_id')->constrained('personal')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['procurador_id', 'abogado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abogado_procurador');
    }
};
