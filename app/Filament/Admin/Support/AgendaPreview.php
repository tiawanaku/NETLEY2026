<?php

namespace App\Filament\Admin\Support;

use App\Models\Cita;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Mini-calendario dinámico (mes/semana/día, navegable con flechas) con las
 * citas ya programadas del personal seleccionado, para evitar sobreponer
 * horarios al agendar una nueva cita. Compartida entre las acciones
 * "Agendar" de Consultas y Casos.
 *
 * La navegación y el cambio de vista ocurren 100% en el cliente (Alpine),
 * sobre un rango de citas precargado de antemano, para no depender de un
 * viaje a Livewire por cada clic en "mes anterior/siguiente".
 */
class AgendaPreview
{
    public static function render(?array $personalIds): HtmlString
    {
        $personalIds = array_filter($personalIds ?? []);

        if (empty($personalIds)) {
            return new HtmlString(
                '<p style="font-size:12px;color:#6b7280;">Selecciona uno o más abogados para ver su agenda.</p>'
            );
        }

        $hoy = Carbon::today();
        $desde = $hoy->copy()->subMonths(2)->startOfMonth();
        $hasta = $hoy->copy()->addMonths(4)->endOfMonth();

        $citasPorDia = Cita::query()
            ->whereHas('personal', fn ($q) => $q->whereIn('personal.id', $personalIds))
            ->where('anulada', false)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->with('personal:id,nombres,ap_paterno')
            ->orderBy('hora')
            ->get()
            ->groupBy(fn (Cita $cita) => $cita->fecha->toDateString())
            ->map(fn (Collection $citas) => $citas->map(function (Cita $cita) {
                $hora = $cita->hora instanceof Carbon
                    ? $cita->hora->format('H:i')
                    : Carbon::parse((string) $cita->hora)->format('H:i');

                return [
                    'hora' => $hora,
                    'nombres' => $cita->personal->map(fn ($p) => $p->nombres.' '.$p->ap_paterno)->implode(', '),
                ];
            })->values())
            ->all();

        return new HtmlString(view('filament.admin.consultas.agenda-preview', [
            'citasPorDia' => $citasPorDia,
            'hoy' => $hoy,
        ])->render());
    }
}
