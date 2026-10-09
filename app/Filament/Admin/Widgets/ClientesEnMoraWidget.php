<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Caso;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Casos con cuotas del plan de pagos vencidas y aún marcadas "pendiente". */
class ClientesEnMoraWidget extends TableWidget
{
    protected static ?string $heading = 'Clientes en mora (cuotas vencidas)';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Caso::query()
                ->whereHas('planesPago', fn ($q) => $q->where('estado', 'pendiente')->where('fecha', '<', now()))
                ->withCount(['planesPago as cuotas_vencidas' => fn ($q) => $q->where('estado', 'pendiente')->where('fecha', '<', now())]))
            ->columns([
                TextColumn::make('cliente.nombre_completo')->label('Cliente'),
                TextColumn::make('cuotas_vencidas')->label('Cuotas vencidas')->badge()->color('danger'),
                TextColumn::make('saldo')->money('BOB'),
            ])
            ->paginated(false);
    }
}
