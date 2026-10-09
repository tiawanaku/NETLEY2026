<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Cita;
use App\Support\AlcancePersonal;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Réplica de "Citas Programadas para Hoy" de dashboard.php. */
class CitasHoyWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getCitasQuery(): Builder
    {
        $ids = AlcancePersonal::idsRelevantes(auth()->user());

        return Cita::query()
            ->whereDate('fecha', now()->toDateString())
            ->where('anulada', false)
            ->when($ids !== null, fn ($q) => $q->whereHas('personal', fn ($q2) => $q2->whereIn('personal.id', $ids)));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('🔵 Citas Programadas para Hoy ('.$this->getCitasQuery()->count().')')
            ->query(fn () => $this->getCitasQuery()->orderBy('hora'))
            ->columns([
                TextColumn::make('hora')->label('Hora')->time()->badge()->color('info'),
                TextColumn::make('consulta.nombre_completo')->label('Cliente')->placeholder('Sin asignar'),
                TextColumn::make('personal.nombres')->label('Personal asignado')->listWithLineBreaks()->limitList(1)->placeholder('Sin asignar'),
                TextColumn::make('detalle')->label('Detalle')->limit(50),
            ])
            ->paginated(false)
            ->emptyStateHeading('No hay citas programadas para hoy')
            ->emptyStateIcon('heroicon-o-calendar');
    }
}
