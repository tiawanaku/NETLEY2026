<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fichas de "Datos judiciales":
 * - FS. Ministerio Público / FS. Juzgado: fojas del cuaderno en la fiscalía
 *   y en el juzgado (columna `fojas`).
 * - Informes pendientes / Alarma: cada "¿Qué sigue?" de un seguimiento
 *   queda pendiente hasta marcarlo cumplido (`proxima_cumplida_at`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscalias', function (Blueprint $table) {
            $table->unsignedInteger('fojas')->nullable()->after('num_caso');
        });

        Schema::table('juzgados', function (Blueprint $table) {
            $table->unsignedInteger('fojas')->nullable()->after('num_caso');
        });

        Schema::table('seguimientos_caso', function (Blueprint $table) {
            $table->timestamp('proxima_cumplida_at')->nullable()->after('proxima_actuacion');
        });

        // Antes solo contaba la próxima actuación del último seguimiento que
        // la tenía: las anteriores ya estaban reemplazadas, se dan por cumplidas.
        $vigentes = DB::table('seguimientos_caso as s')
            ->whereNotNull('s.proxima_fecha')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('seguimientos_caso as t')
                ->whereColumn('t.caso_id', 's.caso_id')
                ->whereNotNull('t.proxima_fecha')
                ->where(fn ($w) => $w->whereColumn('t.fecha_seguimiento', '>', 's.fecha_seguimiento')
                    ->orWhere(fn ($w2) => $w2->whereColumn('t.fecha_seguimiento', 's.fecha_seguimiento')->whereColumn('t.id', '>', 's.id'))))
            ->pluck('s.id');

        DB::table('seguimientos_caso')
            ->whereNotNull('proxima_fecha')
            ->whereNotIn('id', $vigentes)
            ->update(['proxima_cumplida_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('seguimientos_caso', fn (Blueprint $table) => $table->dropColumn('proxima_cumplida_at'));
        Schema::table('juzgados', fn (Blueprint $table) => $table->dropColumn('fojas'));
        Schema::table('fiscalias', fn (Blueprint $table) => $table->dropColumn('fojas'));
    }
};
