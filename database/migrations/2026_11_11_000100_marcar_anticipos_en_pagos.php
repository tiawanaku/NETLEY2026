<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los pagos importados del sistema anterior llegaron todos como "a cuenta"
 * (ver ImportLegacyData::importarPagos(), que no traía el tipo): el primer
 * pago de cada caso (su cuota 1, o 0 en versiones viejas) es el anticipo.
 * Se marca como tal para que aparezca como "Anticipo" en Pagos y en el recibo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $casosConAnticipo = DB::table('pagos')->where('tipo_pago', 'anticipo')->pluck('caso_id');

        $primeros = DB::table('pagos')
            ->whereIn('nro_cuota', [0, 1])
            ->whereNotIn('caso_id', $casosConAnticipo)
            ->selectRaw('MIN(id) as id')
            ->groupBy('caso_id')
            ->pluck('id');

        DB::table('pagos')->whereIn('id', $primeros)->update(['tipo_pago' => 'anticipo']);
    }

    public function down(): void
    {
        // Sin vuelta atrás: no se puede distinguir cuáles eran "a cuenta" de verdad.
    }
};
