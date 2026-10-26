<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacto_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contacto_id')->constrained('contactos')->cascadeOnDelete();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contacto_id', 'personal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacto_personal');
    }
};
