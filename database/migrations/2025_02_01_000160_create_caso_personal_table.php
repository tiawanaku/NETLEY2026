<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caso_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['caso_id', 'personal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caso_personal');
    }
};
