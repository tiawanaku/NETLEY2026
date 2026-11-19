<?php

use App\Models\Caso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Procesos de costas y costos creados antes del 19/11/2026: se guardaron con
 * iguala/saldo = Iguala Netley, sin el patrocinio Hand i Hand, que en costas
 * también se cobra (ver ClienteCasoWizard::plan()). Se suma el patrocinio a
 * la iguala y al saldo, y se reparte entre las cuotas pendientes (las
 * pagadas no se tocan; si no queda ninguna pendiente, se agrega una cuota
 * más). El saldo restante de cada cuota se vuelve a calcular.
 *
 * Ya corregidos (iguala = monto del proceso original + adicional, o marcados
 * por la corrección manual del caso 84 en local) se saltan.
 */
return new class extends Migration
{
    public const MARCA = 'corrección costas (saldo + HiH)';

    public function up(): void
    {
        Caso::withTrashed()
            ->whereNotNull('caso_origen_id')
            ->where('patrocinio_hih', true)
            ->where('monto_patrocinio', '>', 0)
            ->each(function (Caso $caso): void {
                if ($caso->planesPago()->where('creado_por', self::MARCA)->exists()) {
                    return;
                }

                // Sin monto adicional, ya corregido = iguala igual al monto original.
                $origen = Caso::withTrashed()->find($caso->caso_origen_id);
                if ($origen && abs((float) $caso->iguala - $origen->igualaProfesional()) < 0.01) {
                    return;
                }

                DB::transaction(function () use ($caso): void {
                    $hih = (int) round((float) $caso->monto_patrocinio);
                    $iguala = (float) $caso->iguala + $hih;

                    $caso->forceFill(['iguala' => $iguala, 'saldo' => (float) $caso->saldo + $hih])->saveQuietly();

                    $cuotas = $caso->planesPago()->orderBy('numero')->get();
                    $pendientes = $cuotas->filter(fn ($cuota) => $cuota->estado?->value === 'pendiente' || $cuota->estado === 'pendiente')->values();

                    if ($pendientes->isEmpty()) {
                        $ultima = $cuotas->last();
                        $caso->planesPago()->create([
                            'numero' => ($ultima?->numero ?? 0) + 1,
                            'fecha' => ($ultima?->fecha ?? now())->copy()->addMonthNoOverflow()->toDateString(),
                            'monto' => $hih,
                            'nuevo_saldo' => 0,
                            'estado' => 'pendiente',
                            'creado_por' => self::MARCA,
                        ]);
                    } else {
                        $parte = intdiv($hih, $pendientes->count());
                        $resto = $hih - $parte * $pendientes->count();

                        foreach ($pendientes as $i => $cuota) {
                            $cuota->update([
                                'monto' => (float) $cuota->monto + $parte + ($i === $pendientes->count() - 1 ? $resto : 0),
                                'creado_por' => self::MARCA,
                            ]);
                        }
                    }

                    // Saldo restante después de cada cuota, sobre la iguala nueva.
                    $restante = $iguala;
                    foreach ($caso->planesPago()->orderBy('numero')->get() as $cuota) {
                        $restante -= (float) $cuota->monto;
                        $cuota->update(['nuevo_saldo' => max(round($restante, 2), 0)]);
                    }
                });
            });
    }

    public function down(): void
    {
        // Sin vuelta atrás automática.
    }
};
