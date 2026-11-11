<?php

namespace App\Filament\Admin\Support;

use App\Models\Caso;

/**
 * Cuando un pago excede el monto de la cuota que cubre, el excedente se
 * descuenta del saldo pendiente; esto redistribuye ese saldo actualizado en
 * partes iguales entre las cuotas pendientes restantes, para que el plan
 * de pagos siga reflejando exactamente lo que falta cobrar.
 *
 * Cuotas en bolivianos enteros (sin centavos): la última absorbe el resto
 * de la división (y los centavos del saldo, si quedaron de pagos antiguos).
 */
class PlanPagoRecalculo
{
    public static function redistribuir(Caso $caso): void
    {
        $pendientes = $caso->planesPago()->where('estado', 'pendiente')->orderBy('numero')->get();

        if ($pendientes->isEmpty()) {
            return;
        }

        $saldoActual = max((float) $caso->fresh()->saldo, 0);
        $cantidad = $pendientes->count();
        $montoCuota = intdiv((int) floor($saldoActual), $cantidad);
        $restante = $saldoActual;

        foreach ($pendientes as $indice => $plan) {
            $esUltima = $indice === $cantidad - 1;
            $monto = $esUltima ? round($restante, 2) : $montoCuota;
            $restante = round($restante - $monto, 2);

            $plan->update([
                'monto' => $monto,
                'nuevo_saldo' => max($restante, 0),
            ]);
        }
    }
}
