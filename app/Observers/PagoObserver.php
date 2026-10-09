<?php

namespace App\Observers;

use App\Models\Pago;

class PagoObserver
{
    /**
     * Usa decrement/increment atómicos (UPDATE ... SET col = col - X) en vez de
     * leer $pago->caso->saldo y reescribirlo: evita tanto condiciones de carrera
     * como el problema de caché de relación obsoleta si el mismo modelo $pago se
     * reutiliza entre eventos (la propiedad ->caso se cachea en el objeto).
     */
    public function created(Pago $pago): void
    {
        $pago->caso()->decrement('saldo', $pago->monto);
        $pago->caso()->increment('pagado', $pago->monto);
    }

    public function deleted(Pago $pago): void
    {
        $pago->caso()->increment('saldo', $pago->monto);
        $pago->caso()->decrement('pagado', $pago->monto);
    }
}
