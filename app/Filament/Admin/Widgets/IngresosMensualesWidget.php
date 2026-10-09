<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Pago;
use Filament\Widgets\ChartWidget;

class IngresosMensualesWidget extends ChartWidget
{
    protected ?string $heading = 'Ingresos mensuales (últimos 12 meses)';

    protected static bool $isLazy = false;

    protected function getData(): array
    {
        $desde = now()->subMonths(11)->startOfMonth();

        $porMes = Pago::query()
            ->where('fecha_pago', '>=', $desde)
            ->selectRaw('DATE_FORMAT(fecha_pago, "%Y-%m") as mes, SUM(monto) as total')
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $labels = [];
        $data = [];

        for ($i = 0; $i < 12; $i++) {
            $fecha = $desde->copy()->addMonths($i);
            $clave = $fecha->format('Y-m');
            $labels[] = $fecha->translatedFormat('M Y');
            $data[] = (float) ($porMes[$clave] ?? 0);
        }

        return [
            'datasets' => [[
                'label' => 'Ingresos (Bs.)',
                'data' => $data,
                'borderColor' => '#3b82f6',
                'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
