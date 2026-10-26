<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Materia legal "Otros": el caso queda sin especialidad del catálogo y
     * guarda la materia escrita a mano (igual que respuestas.materia_texto).
     */
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->string('especialidad', 20)->nullable()->change();
            $table->string('materia_texto', 120)->nullable()->after('especialidad');
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn('materia_texto');
            $table->string('especialidad', 20)->nullable(false)->change();
        });
    }
};
