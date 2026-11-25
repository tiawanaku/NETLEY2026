<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El sistema ya no maneja centavos: redondea a entero los montos guardados
 * (los porcentajes no se tocan). Después, en los casos cuyo plan de pagos
 * dejó de cuadrar con la iguala por el redondeo, la diferencia va a la
 * última cuota pendiente y se recalcula el "saldo tras esta cuota".
 */
return new class extends Migration
{
    private const MONTOS = [
        'casos' => ['monto_servicio_psicologia', 'monto_servicio_trabajo_social', 'monto_servicio_otro', 'iguala', 'saldo', 'pagado', 'monto_patrocinio', 'comision_monto'],
        'citas' => ['bs_iguala', 'bs_anticipo', 'bs_comision'],
        'informes_cierre_caso' => ['saldo', 'perdida', 'perdida_hih', 'perdida_netley'],
        'informes_cita' => ['anticipo', 'iguala'],
        'llamadas' => ['iguala', 'anticipo', 'comision_bs'],
        'pagos' => ['monto'],
        'planes_pago' => ['monto', 'nuevo_saldo'],
    ];

    public function up(): void
    {
        // Casos con centavos antes de redondear: solo a esos se les revisa el plan.
        $casos = DB::table('planes_pago')->whereRaw('monto <> ROUND(monto, 0)')->pluck('caso_id')
            ->merge(DB::table('casos')->whereRaw('saldo <> ROUND(saldo, 0) OR pagado <> ROUND(pagado, 0)')->pluck('id'))
            ->unique();

        foreach (self::MONTOS as $tabla => $columnas) {
            foreach ($columnas as $columna) {
                if (Schema::hasColumn($tabla, $columna)) {
                    DB::table($tabla)->whereNotNull($columna)
                        ->whereRaw("`{$columna}` <> ROUND(`{$columna}`, 0)")
                        ->update([$columna => DB::raw("ROUND(`{$columna}`, 0)")]);
                }
            }
        }

        foreach ($casos as $casoId) {
            $caso = DB::table('casos')->find($casoId);
            $cuotas = DB::table('planes_pago')->where('caso_id', $casoId)->orderBy('numero')->get();

            if (! $caso || $cuotas->isEmpty()) {
                continue;
            }

            $diferencia = (float) $caso->iguala - (float) $cuotas->sum('monto');
            $ultima = $cuotas->where('estado', 'pendiente')->last();

            if ($diferencia != 0.0 && $ultima && (float) $ultima->monto + $diferencia > 0) {
                DB::table('planes_pago')->where('id', $ultima->id)->update(['monto' => (float) $ultima->monto + $diferencia]);
                $cuotas = DB::table('planes_pago')->where('caso_id', $casoId)->orderBy('numero')->get();
            }

            $corriente = (float) $caso->iguala;

            foreach ($cuotas as $cuota) {
                $corriente -= (float) $cuota->monto;
                DB::table('planes_pago')->where('id', $cuota->id)->update(['nuevo_saldo' => max($corriente, 0)]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible: los centavos originales no se guardan.
    }
};
