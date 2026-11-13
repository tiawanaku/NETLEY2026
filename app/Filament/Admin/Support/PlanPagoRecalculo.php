<?php

namespace App\Filament\Admin\Support;

use App\Enums\EstadoCuota;
use App\Models\Caso;
use App\Models\Pago;
use App\Models\PagoCorreccion;
use App\Models\PlanPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plan de pagos dinámico: el saldo del caso (Iguala Netley − pagado) es
 * siempre la fuente de verdad, y las cuotas pendientes se reparten ese
 * saldo en partes iguales.
 *
 * - pagarCuota(): pagar una cuota con un monto distinto al asignado
 *   recalcula el resto de cuotas pendientes y todos los "saldo restante".
 * - corregirPago(): editar un pago ya registrado (con justificación), que
 *   también reajusta el caso y el plan.
 *
 * Cuotas en bolivianos enteros (sin centavos): la última absorbe el resto
 * de la división (y los centavos del saldo, si quedaron de pagos antiguos).
 */
class PlanPagoRecalculo
{
    /** Primera cuota pendiente del plan: la única que se puede pagar (se paga en orden). */
    public static function proximaCuota(Caso $caso): ?PlanPago
    {
        return $caso->planesPago()->where('estado', EstadoCuota::Pendiente)->orderBy('numero')->first();
    }

    /** ¿Es la última cuota pendiente? Entonces debe cubrir todo el saldo. */
    public static function esUltimaPendiente(PlanPago $cuota): bool
    {
        return ! PlanPago::where('caso_id', $cuota->caso_id)
            ->where('estado', EstadoCuota::Pendiente)
            ->where('numero', '>', $cuota->numero)
            ->exists();
    }

    /**
     * Cómo quedarían las demás cuotas pendientes si se paga $monto en esta.
     *
     * @return array{restantes: int, monto_cuota: int, ultima: float, saldo: float}
     */
    public static function vistaPrevia(PlanPago $cuota, float $monto): array
    {
        $saldo = max(round((float) $cuota->caso->fresh()->saldo - $monto, 2), 0);
        $restantes = PlanPago::where('caso_id', $cuota->caso_id)
            ->where('estado', EstadoCuota::Pendiente)
            ->where('id', '!=', $cuota->id)
            ->count();
        $montoCuota = $restantes > 0 ? intdiv((int) floor($saldo), $restantes) : 0;

        return [
            'restantes' => $restantes,
            'monto_cuota' => $montoCuota,
            'ultima' => $restantes > 0 ? round($saldo - $montoCuota * ($restantes - 1), 2) : 0,
            'saldo' => $saldo,
        ];
    }

    /**
     * Registra el pago de una cuota (la primera pendiente). Si el monto no es
     * el asignado, la cuota queda con lo pagado y el resto del saldo se
     * reparte entre las cuotas pendientes que siguen.
     *
     * @param  array<string, mixed>  $datosPago  forma de pago, fecha, sucursal… (ver CamposPago)
     */
    public static function pagarCuota(PlanPago $cuota, float $monto, array $datosPago): Pago
    {
        return DB::transaction(function () use ($cuota, $monto, $datosPago): Pago {
            $caso = $cuota->caso()->lockForUpdate()->first();
            $saldo = (float) $caso->saldo;
            self::validarPago($cuota, $monto, $saldo);

            $pago = Pago::create([
                'caso_id' => $caso->id,
                'cliente_id' => $caso->cliente_id,
                'monto' => $monto,
                ...$datosPago,
                'nro_cuota' => $cuota->numero,
                'nro_recibo' => ((int) Pago::max('nro_recibo')) + 1,
                'registrado_por' => auth()->user()?->username,
            ]);

            $cuota->update(['monto' => $monto, 'estado' => EstadoCuota::Pagado]);

            self::recalcular($caso->fresh());

            return $pago;
        });
    }

    /** Restricciones de un pago de cuota (ver pagarCuota()). */
    public static function validarPago(PlanPago $cuota, float $monto, float $saldo): void
    {
        $proxima = self::proximaCuota($cuota->caso);

        if (! $proxima || $proxima->id !== $cuota->id) {
            throw ValidationException::withMessages(['monto' => 'Las cuotas se pagan en orden: primero la cuota '.($proxima?->numero ?? '—').'.']);
        }

        if ($monto <= 0) {
            throw ValidationException::withMessages(['monto' => 'El monto debe ser mayor a 0.']);
        }

        if ($monto > $saldo + 0.001) {
            throw ValidationException::withMessages(['monto' => 'No puede pagar más del saldo pendiente (Bs. '.self::bs($saldo).').']);
        }

        if (self::esUltimaPendiente($cuota) && abs($monto - $saldo) > 0.001) {
            throw ValidationException::withMessages(['monto' => 'Es la última cuota: debe cubrir todo el saldo pendiente (Bs. '.self::bs($saldo).').']);
        }
    }

    /**
     * Corrige un pago ya registrado. Guarda la justificación y lo que cambió
     * (PagoCorreccion), ajusta pagado/saldo del caso por la diferencia y
     * vuelve a calcular el plan.
     *
     * @param  array<string, mixed>  $datos  monto, fecha_pago, forma_pago, nro_cheque, banco, sucursal
     */
    public static function corregirPago(Pago $pago, array $datos, string $justificacion): Pago
    {
        return DB::transaction(function () use ($pago, $datos, $justificacion): Pago {
            $caso = $pago->caso()->lockForUpdate()->first();
            $montoAnterior = (float) $pago->monto;
            $montoNuevo = (float) $datos['monto'];
            $diferencia = round($montoNuevo - $montoAnterior, 2);

            if ($montoNuevo <= 0) {
                throw ValidationException::withMessages(['monto' => 'El monto debe ser mayor a 0.']);
            }

            if ($diferencia > (float) $caso->saldo + 0.001) {
                throw ValidationException::withMessages(['monto' => 'Con ese monto se pagaría más que la Iguala Netley: el máximo es Bs. '.self::bs($montoAnterior + (float) $caso->saldo).'.']);
            }

            $campos = ['monto', 'fecha_pago', 'forma_pago', 'nro_cheque', 'banco', 'sucursal'];
            $antes = collect($pago->only($campos))->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v)->all();

            $pago->update(collect($datos)->only($campos)->all());

            $despues = collect($pago->fresh()->only($campos))->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v)->all();

            PagoCorreccion::create([
                'pago_id' => $pago->id,
                'caso_id' => $caso->id,
                'justificacion' => trim($justificacion),
                'antes' => $antes,
                'despues' => $despues,
                'corregido_por' => auth()->user()?->username,
            ]);

            // El PagoObserver solo ajusta al crear/borrar: acá la diferencia.
            if ($diferencia != 0.0) {
                $caso->decrement('saldo', $diferencia);
                $caso->increment('pagado', $diferencia);

                // La cuota pagada con este pago refleja el monto corregido.
                $caso->planesPago()->where('numero', $pago->nro_cuota)->where('estado', EstadoCuota::Pagado)
                    ->update(['monto' => $montoNuevo]);
            }

            self::recalcular($caso->fresh());

            return $pago->fresh();
        });
    }

    /**
     * Reparte el saldo actual entre las cuotas pendientes y recalcula el
     * "saldo restante" de todas las filas, en orden.
     *
     * - Si el saldo llegó a 0, las cuotas pendientes que sobran se quitan.
     * - Si queda saldo pero ya no hay cuotas pendientes (ej. se corrigió un
     *   pago a menos), se agrega una cuota al final, un mes después.
     */
    public static function recalcular(Caso $caso): void
    {
        $saldo = max(round((float) $caso->saldo, 2), 0);
        $pendientes = $caso->planesPago()->where('estado', EstadoCuota::Pendiente)->orderBy('numero')->get();

        if ($saldo <= 0) {
            $caso->planesPago()->where('estado', EstadoCuota::Pendiente)->delete();
            $pendientes = collect();
        } elseif ($pendientes->isEmpty()) {
            $ultima = $caso->planesPago()->orderByDesc('numero')->first();
            $pendientes = collect([$caso->planesPago()->create([
                'numero' => ((int) ($ultima?->numero ?? 0)) + 1,
                'fecha' => ($ultima?->fecha ?? now())->copy()->addMonthNoOverflow(),
                'monto' => $saldo,
                'nuevo_saldo' => 0,
                'estado' => EstadoCuota::Pendiente,
                'creado_por' => auth()->user()?->username,
            ])]);
        }

        $cantidad = $pendientes->count();

        if ($cantidad > 0) {
            $montoCuota = intdiv((int) floor($saldo), $cantidad);
            $restante = $saldo;

            foreach ($pendientes->values() as $indice => $plan) {
                $monto = $indice === $cantidad - 1 ? round($restante, 2) : $montoCuota;
                $restante = round($restante - $monto, 2);
                $plan->update(['monto' => $monto]);
            }
        }

        // "Saldo restante" de cada fila, en orden, partiendo de la Iguala
        // Netley: así cuadra tanto en las pagadas como en las pendientes.
        $corriente = (float) $caso->iguala;

        foreach ($caso->planesPago()->orderBy('numero')->get() as $plan) {
            $corriente = round($corriente - (float) $plan->monto, 2);
            $plan->update(['nuevo_saldo' => max($corriente, 0)]);
        }
    }

    /** Compatibilidad: antes solo se usaba al pagar de más. */
    public static function redistribuir(Caso $caso): void
    {
        self::recalcular($caso->fresh());
    }

    public static function bs(float $monto): string
    {
        return fmod($monto, 1.0) == 0.0 ? number_format($monto, 0, '.', '') : number_format($monto, 2, '.', '');
    }
}
