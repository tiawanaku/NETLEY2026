<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detalle del origen de una consulta o llamada. Hoy solo se usa con el
     * origen "Talleres" (qué taller: colegio, institución, fecha...); con
     * cualquier otro origen queda en null (ver App\Support\Origenes).
     */
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->string('origen_detalle', 255)->nullable()->after('origen');
        });

        Schema::table('llamadas', function (Blueprint $table) {
            $table->string('origen_detalle', 255)->nullable()->after('origen');
        });
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropColumn('origen_detalle');
        });

        Schema::table('llamadas', function (Blueprint $table) {
            $table->dropColumn('origen_detalle');
        });
    }
};
