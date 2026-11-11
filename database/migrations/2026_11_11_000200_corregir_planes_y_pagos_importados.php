<?php

use App\Filament\Admin\Support\PlanPagoRecalculo;
use App\Models\Caso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * En el sistema anterior, "nro_cuota" de un pago era solo su orden (1.º,
 * 2.º, 3.º pago), no la cuota del plan que pagaba. Importados así, el
 * anticipo y los pagos "a cuenta" quedaban asociados a cuotas del plan que
 * en realidad siguen pendientes. Por cada caso con plan de pagos:
 *
 * 1. Si un pago apunta a una cuota del plan que sigue PENDIENTE, no es el
 *    pago de esa cuota: pasa a nro_cuota 0 (anticipo o "a cuenta").
 * 2. Si el caso tiene anticipo y el plan no tiene una fila que lo
 *    represente, se agrega la fila "Anticipo" (n.º 0, pagada).
 * 3. Si las cuotas pendientes no suman el saldo (pagos "a cuenta" que
 *    nunca se descontaron del plan), se reparte el saldo real entre ellas.
 *
 * No toca montos de pagos ni el pagado/saldo del caso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Caso::has('planesPago')->with(['pagos', 'planesPago'])->get()->each(function (Caso $caso): void {
            $pendientes = $caso->planesPago
                ->filter(fn ($cuota) => $this->estado($cuota) === 'pendiente')
                ->pluck('numero')
                ->map(fn ($n) => (int) $n)
                ->all();

            foreach ($caso->pagos as $pago) {
                if ((int) $pago->nro_cuota > 0 && in_array((int) $pago->nro_cuota, $pendientes, true)) {
                    DB::table('pagos')->where('id', $pago->id)->update(['nro_cuota' => 0]);
                    $pago->nro_cuota = 0;
                }
            }

            $anticipo = $caso->pagos->firstWhere('tipo_pago', 'anticipo');
            $representado = $anticipo && $caso->planesPago->contains(fn ($cuota) => (int) $cuota->numero === (int) $anticipo->nro_cuota);

            if ($anticipo && (int) $anticipo->nro_cuota === 0 && ! $representado) {
                $netley = (float) $caso->iguala - (float) ($caso->monto_patrocinio ?? 0);

                $caso->planesPago()->create([
                    'numero' => 0,
                    'fecha' => $anticipo->fecha_pago,
                    'monto' => $anticipo->monto,
                    'nuevo_saldo' => max(round($netley - (float) $anticipo->monto, 2), 0),
                    'estado' => 'pagado',
                    'creado_por' => $anticipo->registrado_por,
                ]);
            }

            $sumaPendiente = (float) $caso->planesPago()->where('estado', 'pendiente')->sum('monto');

            if (abs($sumaPendiente - (float) $caso->saldo) > 0.5) {
                PlanPagoRecalculo::redistribuir($caso);
            }
        });
    }

    public function down(): void
    {
        // Corrección de datos: sin vuelta atrás.
    }

    private function estado($cuota): string
    {
        return $cuota->estado instanceof \BackedEnum ? $cuota->estado->value : (string) $cuota->estado;
    }
};
