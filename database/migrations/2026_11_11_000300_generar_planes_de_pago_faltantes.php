<?php

use App\Models\Caso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Los casos importados del sistema anterior tienen anticipo y saldo, pero
 * no plan de cuotas (el sistema viejo no lo generaba). Se les arma con la
 * misma regla que usa hoy el alta de un caso (ClienteCasoWizard::plan()),
 * con las fechas reales del proceso:
 *
 * - Fila "Anticipo" (n.º 0, pagada) con el pago marcado como anticipo.
 * - Cuotas = meses del proceso (inicio → vencimiento) menos 1, mínimo 1,
 *   mensuales desde un mes después del anticipo.
 * - Bolivianos enteros: la última cuota absorbe el resto (y los centavos
 *   del saldo que dejaron algunos pagos antiguos).
 *
 * Solo toca casos con saldo pendiente y SIN ninguna fila de plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Caso::where('saldo', '>', 0)->doesntHave('planesPago')->with('pagos')->get()->each(function (Caso $caso): void {
            DB::transaction(function () use ($caso): void {
                $anticipo = $caso->pagos->firstWhere('tipo_pago', 'anticipo');
                $saldo = round((float) $caso->saldo, 2);
                $base = Carbon::parse($anticipo?->fecha_pago ?? $caso->fecha_inicio ?? $caso->created_at)->startOfDay();

                $meses = ($caso->fecha_inicio && $caso->fecha_fin)
                    ? max((int) round($caso->fecha_inicio->floatDiffInMonths($caso->fecha_fin)), 1)
                    : max((int) $caso->duracion_meses, 1);
                $cuotas = max($meses - 1, 1);

                if ($anticipo) {
                    $caso->planesPago()->create([
                        'numero' => 0,
                        'fecha' => $base->toDateString(),
                        'monto' => $anticipo->monto,
                        'nuevo_saldo' => $saldo,
                        'estado' => 'pagado',
                        'creado_por' => $anticipo->registrado_por,
                    ]);

                    DB::table('pagos')->where('id', $anticipo->id)->update(['nro_cuota' => 0]);
                }

                $montoCuota = intdiv((int) floor($saldo), $cuotas);
                $restante = $saldo;

                for ($i = 1; $i <= $cuotas; $i++) {
                    $monto = $i === $cuotas ? round($restante, 2) : $montoCuota;
                    $restante = round($restante - $monto, 2);

                    $caso->planesPago()->create([
                        'numero' => $i,
                        'fecha' => $base->copy()->addMonthsNoOverflow($i)->toDateString(),
                        'monto' => $monto,
                        'nuevo_saldo' => max($restante, 0),
                        'estado' => 'pendiente',
                    ]);
                }

                if (! $caso->duracion_meses) {
                    DB::table('casos')->where('id', $caso->id)->update(['duracion_meses' => $meses]);
                }
            });
        });
    }

    public function down(): void
    {
        // Corrección de datos: sin vuelta atrás.
    }
};
