<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Caso;
use Filament\Widgets\ChartWidget;

class TopDelitosWidget extends ChartWidget
{
    protected ?string $heading = 'Top 10 materia + delito más frecuentes';

    protected static bool $isLazy = false;

    protected function getData(): array
    {
        $top = Caso::query()
            ->join('delitos', 'delitos.id', '=', 'casos.delito_id')
            ->selectRaw('delitos.area, delitos.delito, count(*) as total')
            ->groupBy('delitos.id', 'delitos.area', 'delitos.delito')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Casos',
                'data' => $top->pluck('total')->toArray(),
                'backgroundColor' => '#3b82f6',
            ]],
            'labels' => $top->map(fn ($r) => "{$r->area} — ".str($r->delito)->limit(30))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y'];
    }
}
