<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Llamada;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Réplica de "Llamadas Programadas para Hoy" de dashboard.php. */
class LlamadasHoyWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getLlamadasQuery(): Builder
    {
        return Llamada::query()->whereDate('fecha', now()->toDateString());
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('🔵 Llamadas Programadas para Hoy ('.$this->getLlamadasQuery()->count().')')
            ->query(fn () => $this->getLlamadasQuery()->orderBy('hora'))
            ->columns([
                TextColumn::make('hora')->label('Hora')->time()->badge()->color('info'),
                TextColumn::make('telefono')->label('Teléfono'),
                TextColumn::make('nombre_completo')->label('Nombre'),
                TextColumn::make('motivo')->label('Motivo')->limit(40),
                TextColumn::make('creado_por')->label('Creado por'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No hay llamadas programadas para hoy')
            ->emptyStateIcon('heroicon-o-phone');
    }
}
