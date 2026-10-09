<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\CasosPorEstadoWidget;
use App\Filament\Admin\Widgets\ClientesEnMoraWidget;
use App\Filament\Admin\Widgets\IngresosMensualesWidget;
use App\Filament\Admin\Widgets\TopDelitosWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Reemplaza estadisticas.php: KPIs y gráficos, según la matriz de privilegios. */
class Estadisticas extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Estadísticas';

    protected static \UnitEnum|string|null $navigationGroup = 'Estadísticas';

    protected static ?int $navigationSort = 70;

    protected string $view = 'filament-panels::pages.page';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('estadisticas') ?? false;
    }

    protected function getFooterWidgets(): array
    {
        return [
            CasosPorEstadoWidget::class,
            IngresosMensualesWidget::class,
            TopDelitosWidget::class,
            ClientesEnMoraWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }
}
