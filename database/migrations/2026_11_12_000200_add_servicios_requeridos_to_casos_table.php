<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Servicios requeridos" del caso (paso "Proceso" del formulario de Cliente
 * ejecutivo): los mismos de la cita (Psicología, Trabajo social, Otro), que
 * se precargan desde la cita de la consulta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->boolean('servicio_psicologia')->default(false)->after('apersonamiento');
            $table->boolean('servicio_trabajo_social')->default(false)->after('servicio_psicologia');
            $table->boolean('servicio_otro')->default(false)->after('servicio_trabajo_social');
            $table->string('servicio_otro_detalle')->nullable()->after('servicio_otro');
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn(['servicio_psicologia', 'servicio_trabajo_social', 'servicio_otro', 'servicio_otro_detalle']);
        });
    }
};
