<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\EstadoCaso;
use App\Filament\Admin\Resources\Casos\Actions\CasoActions;
use App\Models\Caso;
use App\Support\AlcancePersonal;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Réplica de "Casos Próximos a Vencer" de dashboard.php: alerta-alta (≤5), alerta-media (≤10), alerta-baja (>10). */
class CasosProximosWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getCasosQuery(): Builder
    {
        $ids = AlcancePersonal::idsRelevantes(auth()->user());

        return Caso::query()
            ->where('estado', '!=', EstadoCaso::Cerrado)
            ->whereNotNull('fecha_fin')
            ->whereBetween('fecha_fin', [now()->toDateString(), now()->addDays(15)->toDateString()])
            ->when($ids !== null, fn ($q) => $q->whereHas('personal', fn ($q2) => $q2->whereIn('personal.id', $ids)));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('🟡 Casos Próximos a Vencer ('.$this->getCasosQuery()->count().')')
            ->query(fn () => $this->getCasosQuery()->orderBy('fecha_fin'))
            ->columns([
                TextColumn::make('id')->label('ID Caso')->weight('bold'),
                TextColumn::make('cliente.nombre_completo')->label('Cliente')->placeholder('Sin asignar'),
                TextColumn::make('personal.nombres')->label('Personal asignado')->listWithLineBreaks()->limitList(1)->placeholder('Sin asignar'),
                TextColumn::make('descripcion')->label('Descripción')->limit(40),
                TextColumn::make('fecha_fin')->label('Fecha fin')->date(),
                TextColumn::make('fecha_fin')
                    ->label('Días restantes')
                    ->state(fn (Caso $record) => (int) round(now()->diffInDays($record->fecha_fin, false)))
                    ->formatStateUsing(fn (int $state) => "{$state} días")
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state <= 5 => 'danger',
                        $state <= 10 => 'warning',
                        default => 'success',
                    }),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                CasoActions::verCliente(),
            ])
            ->paginated(false)
            ->emptyStateHeading('No hay casos próximos a vencer en los siguientes 15 días')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
