<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Expedido en" → "Otros" guarda lo escrito (ej. "Extranjero", "Perú"),
     * que no entra en los 10 caracteres de las siglas.
     */
    public function up(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->string('ci_expedido', 60)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->string('ci_expedido', 10)->nullable()->change();
        });
    }
};
