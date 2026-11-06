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
        Table::configureUsing(fn (Table $table) => $table->selectable(false));
    }
}
