<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\EstadoCaso;
use App\Models\Caso;
use Filament\Widgets\ChartWidget;

class CasosPorEstadoWidget extends ChartWidget
{
    protected ?string $heading = 'Casos por estado';

    protected static bool $isLazy = false;

    protected function getData(): array
    {
        $conteo = Caso::query()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        $estados = EstadoCaso::cases();

        return [
            'datasets' => [[
                'label' => 'Casos',
                'data' => collect($estados)->map(fn ($e) => $conteo[$e->value] ?? 0)->toArray(),
                'backgroundColor' => ['#f59e0b', '#3b82f6', '#22c55e'],
            ]],
            'labels' => collect($estados)->map(fn ($e) => $e->getLabel())->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
