<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lista de redes sociales de la persona (puede tener varias): cada item
     * guarda {plataforma, usuario} — ver App\Enums\RedSocialPlataforma.
     */
    public function up(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->json('redes_sociales')->nullable()->after('correo');
        });
    }

    public function down(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->dropColumn('redes_sociales');
        });
    }
};
