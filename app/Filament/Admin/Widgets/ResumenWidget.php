<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\EstadoCaso;
use App\Enums\EstadoConsulta;
use App\Models\Caso;
use App\Models\Consulta;
use App\Models\Pago;
use App\Support\AlcancePersonal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Reemplaza las tarjetas de resumen de dashboard.php. Se recorta a los casos
 * asignados cuando el usuario es Abogado o Procuradora, igual que el resto
 * del panel.
 */
class ResumenWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $ids = AlcancePersonal::idsRelevantes(auth()->user());

        $casos = Caso::query()
            ->when($ids !== null, fn ($q) => $q->whereHas('personal', fn ($q2) => $q2->whereIn('personal.id', $ids)));

        return [
            Stat::make('Casos activos', (clone $casos)->where('estado', '!=', EstadoCaso::Cerrado)->count())
                ->color('primary'),
            Stat::make('Casos vencidos', (clone $casos)
                ->where('estado', '!=', EstadoCaso::Cerrado)
                ->whereNotNull('fecha_fin')
                ->where('fecha_fin', '<', now())
                ->count())
                ->color('danger'),
            Stat::make('Consultas pendientes', Consulta::whereIn('estado', array_map(fn ($e) => $e->value, EstadoConsulta::abiertos()))->count())
                ->color('warning'),
            Stat::make('Ingresos del mes', 'Bs. '.number_format(Pago::whereMonth('fecha_pago', now()->month)->whereYear('fecha_pago', now()->year)->sum('monto'), 2))
                ->color('success'),
        ];
    }
}
