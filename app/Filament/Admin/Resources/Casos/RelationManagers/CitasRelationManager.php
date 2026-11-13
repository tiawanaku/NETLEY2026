<?php

namespace App\Filament\Admin\Resources\Casos\RelationManagers;

use App\Filament\Admin\Resources\Casos\Actions\CasoActions;
use App\Models\Caso;
use App\Models\Cita;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pestaña "Citas" de la ficha del caso: las citas que tuvo el cliente con
 * el personal — las agendadas para el caso y las de su consulta de origen
 * (antes de ser cliente ejecutivo) — y el botón para agendar una nueva
 * (reemplaza al "Agendar cita" que estaba arriba en la ficha).
 */
class CitasRelationManager extends RelationManager
{
    protected static string $relationship = 'citas';

    protected static ?string $title = 'Citas';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-calendar-days';

    public function table(Table $table): Table
    {
        /** @var Caso $caso */
        $caso = $this->getOwnerRecord();
        $consultas = $caso->consultas()->pluck('id');

        return $table
            ->recordTitleAttribute('fecha')
            // También las citas de la consulta con la que empezó el caso.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->orWhereIn('consulta_id', $consultas)
                ->with('personal'))
            ->columns([
                TextColumn::make('fecha')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('hora')
                    ->label('Hora')
                    ->formatStateUsing(fn ($state) => $state ? substr((string) $state, 0, 5) : '—'),
                TextColumn::make('personal.nombre_completo')
                    ->label('Con')
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextColumn::make('detalle')->label('Nota')->wrap()->limit(80)->placeholder('—'),
                TextColumn::make('origen_cita')
                    ->label('Origen')
                    ->state(fn (Cita $record) => $record->caso_id ? 'Caso' : 'Consulta')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Caso' ? 'info' : 'gray'),
                TextColumn::make('estado_cita')
                    ->label('Estado')
                    ->state(fn (Cita $record) => match (true) {
                        $record->anulada => 'Anulada',
                        $record->fecha?->isPast() && ! $record->fecha->isToday() => 'Realizada',
                        $record->confirmada => 'Confirmada',
                        default => 'Programada',
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Anulada' => 'danger',
                        'Realizada' => 'gray',
                        'Confirmada' => 'success',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('fecha', 'desc')
            ->headerActions([
                CasoActions::agendarCita()->label('Agendar nueva cita'),
            ])
            ->emptyStateHeading('Todavía no hay citas')
            ->emptyStateDescription('Agenda la primera con "Agendar nueva cita".');
    }
}
