<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            // Observación propia del cierre de caso (ver FichaAgendamiento),
            // independiente de "detalle" (Nota de la cita al agendarla).
            $table->text('observacion_cierre')->nullable()->after('observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropColumn('observacion_cierre');
        });
    }
};
