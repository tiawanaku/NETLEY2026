<?php

namespace App\Providers;

use App\Models\Pago;
use App\Observers\PagoObserver;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Pago::observe(PagoObserver::class);

        // Sin casillas de selección (ni la de "marcar todos") en ninguna
        // tabla del panel: no se usan las acciones masivas.
        // Paginación: siempre 50 por página, sin selector de cantidad (con
        // una sola opción la barra compacta « ‹ … › » no lo muestra, ver
        // resources/views/filament/admin/paginacion-compacta.blade.php).
        // Las tablas con ->paginated(false) (widgets) siguen sin paginar.
        Table::configureUsing(fn (Table $table) => $table
            ->selectable(false)
            ->paginated([50])
            ->defaultPaginationPageOption(50));
    }
}
