<?php

namespace App\Filament\Admin\Resources\Consultas\Tables;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Consultas\Actions\ReactivarConsultaAction;
use App\Filament\Admin\Resources\Consultas\ConsultaResource;
use App\Filament\Admin\Support\FiltrosContacto;
use App\Filament\Admin\Support\TelefonoColumna;
use App\Models\Consulta;
use App\Support\Origenes;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConsultasTable
{
    public static function configure(Table $table): Table
    {
        $columnaId = TextColumn::make('id')->label('N°')->sortable();

        $columnaFechaConsulta = TextColumn::make('fecha_consulta')
            ->label('F.Consulta')
            ->date('d/m/Y')
            ->sortable();

        $columnaNombre = TextColumn::make('nombre_completo')
            ->label('Nombre')
            ->searchable(['nombres', 'ap_paterno', 'ap_materno']);

        $columnaWhatsapp = TextColumn::make('whatsapp')
            ->label('WhatsApp')
            ->formatStateUsing(fn (?string $state) => TelefonoColumna::html($state))
            ->html()
            ->searchable();

        $columnaTelefono = TextColumn::make('telefono')
            ->label('Teléfono')
            ->formatStateUsing(fn (?string $state) => TelefonoColumna::html($state))
            ->html()
            ->searchable();

        $columnaCiudad = TextColumn::make('ciudad')
            ->label('Ciudad')
            ->searchable();

        $columnaOrigen = TextColumn::make('origen')
            // "Talleres — <detalle del taller>".
            ->formatStateUsing(fn (?string $state, Consulta $record): ?string => Origenes::texto($state, $record->origen_detalle))
            ->wrap();

        $columnaEstado = TextColumn::make('estado')
            ->badge()
            // En "Agendados" siempre se lee "Agendado" (si está acá
            // es porque tiene una cita vigente, ver ListConsultas) y
            // en "Nuevas" siempre se lee "Pendiente" (si está acá es
            // porque todavía no tiene cita), aunque el estado real
            // guardado sea otro — solo cambia el texto/color
            // mostrados, no el dato guardado.
            ->formatStateUsing(fn (EstadoConsulta $state, $livewire) => match ($livewire->activeTab ?? null) {
                'agendados' => EstadoConsulta::Agendado->getLabel(),
                'nuevas' => EstadoConsulta::Pendiente->getLabel(),
                default => $state->getLabel(),
            })
            ->color(fn (EstadoConsulta $state, $livewire) => match ($livewire->activeTab ?? null) {
                'agendados' => EstadoConsulta::Agendado->getColor(),
                'nuevas' => EstadoConsulta::Pendiente->getColor(),
                default => $state->getColor(),
            });

        // "Agendados" necesita datos de la cita (fecha, hora, abogado) que
        // las demás pestañas no usan, y en un orden distinto al resto —
        // por eso arma su propio arreglo de columnas en vez de solo
        // esconder/mostrar con ->visible().
        $columnas = (($table->getLivewire()->activeTab ?? null) === 'agendados')
            ? [
                $columnaId,
                $columnaNombre,
                $columnaWhatsapp,
                $columnaTelefono,
                $columnaCiudad,
                $columnaOrigen,
                $columnaFechaConsulta,
                TextColumn::make('citaVigente.fecha')
                    ->label('F.Cita')
                    ->date('d/m/Y'),
                TextColumn::make('citaVigente.hora')
                    ->label('Hora')
                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? substr($state, 0, 5) : null),
                TextColumn::make('abogado')
                    ->label('Abogado')
                    ->state(fn (Consulta $record): string => $record->citaVigente?->personal
                        ->pluck('nombre_completo')
                        ->join(', ') ?: '—'),
                $columnaEstado,
            ]
            : [
                $columnaId,
                $columnaFechaConsulta,
                $columnaNombre,
                $columnaWhatsapp,
                $columnaTelefono,
                $columnaCiudad,
                $columnaOrigen,
                $columnaEstado,
            ];

        return $table
            ->columns($columnas)
            ->defaultSort('fecha_consulta', 'desc')
            ->filters([
                SelectFilter::make('estado')->options(EstadoConsulta::class),
                ...FiltrosContacto::make(),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                EditAction::make()
                    ->label('Ver')
                    // Desde "Agendados" abre VerAgendado, una ficha aparte
                    // (copia de EditConsulta) para poder seguir
                    // modificándola sin afectar la ficha normal que usan
                    // "Nuevas"/"Cerradas". Con "Ver formulario" (dentro de
                    // esa ficha) se llega a FichaAgendamiento.
                    ->url(fn (Consulta $record, $livewire): string => ($livewire->activeTab ?? null) === 'agendados'
                        ? ConsultaResource::getUrl('verAgendado', ['record' => $record])
                        : ConsultaResource::getUrl('edit', ['record' => $record])),
                ReactivarConsultaAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
                ]),
            ]);
    }
}
