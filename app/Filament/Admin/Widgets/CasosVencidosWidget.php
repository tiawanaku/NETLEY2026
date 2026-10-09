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

/** Réplica de la sección "Casos Vencidos" (alerta-roja) de dashboard.php. */
class CasosVencidosWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getCasosQuery(): Builder
    {
        $ids = AlcancePersonal::idsRelevantes(auth()->user());

        return Caso::query()
            ->where('estado', '!=', EstadoCaso::Cerrado)
            ->whereNotNull('fecha_fin')
            ->where('fecha_fin', '<', now()->toDateString())
            ->when($ids !== null, fn ($q) => $q->whereHas('personal', fn ($q2) => $q2->whereIn('personal.id', $ids)));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('🔴 Casos Vencidos ('.$this->getCasosQuery()->count().')')
            ->query(fn () => $this->getCasosQuery()->orderBy('fecha_fin'))
            ->columns([
                TextColumn::make('id')->label('ID Caso')->weight('bold'),
                TextColumn::make('cliente.nombre_completo')->label('Cliente')->placeholder('Sin asignar'),
                TextColumn::make('personal.nombres')->label('Personal asignado')->listWithLineBreaks()->limitList(1)->placeholder('Sin asignar'),
                TextColumn::make('descripcion')->label('Descripción')->limit(40),
                TextColumn::make('fecha_fin')->label('Fecha fin')->date(),
                TextColumn::make('fecha_fin')
                    ->label('Estado')
                    ->state(fn (Caso $record) => 'Vencido hace '.abs((int) round(now()->diffInDays($record->fecha_fin, false))).' días')
                    ->badge()
                    ->color('danger'),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                CasoActions::verCliente(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Sin casos vencidos')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
