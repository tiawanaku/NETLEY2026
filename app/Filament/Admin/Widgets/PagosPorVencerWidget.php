<?php

namespace App\Filament\Admin\Widgets;

use App\Models\PlanPago;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Réplica de "Pagos por vencer en 2 días" de dashboard.php. */
class PagosPorVencerWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getPlanesQuery(): Builder
    {
        return PlanPago::query()
            ->whereDate('fecha', now()->addDays(2)->toDateString())
            ->where(fn ($q) => $q->whereNull('estado')->orWhere('estado', 'pendiente'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('🟢 Pagos por Vencer en 2 Días ('.$this->getPlanesQuery()->count().')')
            ->query(fn () => $this->getPlanesQuery()->orderBy('fecha'))
            ->columns([
                TextColumn::make('caso_id')->label('Caso')->formatStateUsing(fn ($state) => "#{$state}"),
                TextColumn::make('caso.cliente.nombre_completo')->label('Cliente'),
                TextColumn::make('caso.descripcion')->label('Descripción')->limit(40),
                TextColumn::make('numero')->label('N° cuota'),
                TextColumn::make('fecha')->date(),
                TextColumn::make('monto')->money('BOB')->badge()->color('success'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No hay pagos por vencer en 2 días')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
