<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\CasosProximosWidget;
use App\Filament\Admin\Widgets\CasosVencidosWidget;
use App\Filament\Admin\Widgets\CitasHoyWidget;
use App\Filament\Admin\Widgets\LlamadasHoyWidget;
use App\Filament\Admin\Widgets\PagosPorVencerWidget;
use App\Filament\Admin\Widgets\ResumenWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

/**
 * Reemplaza dashboard.php: mismas 5 secciones de alerta, en el mismo orden
 * (Vencidos / Próximos / Citas hoy / Llamadas hoy / Pagos por vencer).
 * Los widgets financieros/gráficos (CasosPorEstado, IngresosMensuales,
 * TopDelitos, ClientesEnMora) quedan solo en Estadísticas.
 */
class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            ResumenWidget::class,
            CasosVencidosWidget::class,
            CasosProximosWidget::class,
            CitasHoyWidget::class,
            LlamadasHoyWidget::class,
            PagosPorVencerWidget::class,
        ];
    }
}
