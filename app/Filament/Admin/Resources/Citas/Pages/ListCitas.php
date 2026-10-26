<?php

namespace App\Filament\Admin\Resources\Citas\Pages;

use App\Filament\Admin\Pages\AgendaCalendario;
use App\Filament\Admin\Resources\Citas\CitaResource;
use App\Models\Cita;
use App\Models\Personal;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListCitas extends ListRecords
{
    protected static string $resource = CitaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verCalendario')
                ->label('Ver calendario')
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->url(fn () => AgendaCalendario::getUrl()),
            Action::make('vistaMes')
                ->label('Mes')
                ->color('gray')
                ->url(fn () => AgendaCalendario::getUrl(['vista' => 'mes'])),
            Action::make('vistaSemana')
                ->label('Semana')
                ->color('gray')
                ->url(fn () => AgendaCalendario::getUrl(['vista' => 'semana'])),
            Action::make('vistaDia')
                ->label('Día')
                ->color('gray')
                ->url(fn () => AgendaCalendario::getUrl(['vista' => 'dia'])),
            Action::make('imprimir')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(fn () => $this->imprimirAgenda()),
            CreateAction::make(),
        ];
    }

    /**
     * Imprime todas las citas activas (respetando el filtro de "Abogado" de
     * la tabla si está activo), agrupadas por día — mismo formato que el
     * botón "Imprimir" del calendario.
     */
    protected function imprimirAgenda(): mixed
    {
        $personalId = $this->tableFilters['personal']['value'] ?? null;
        $abogado = $personalId ? Personal::find($personalId) : null;

        $dias = Cita::query()
            ->where('anulada', false)
            ->when(
                $personalId,
                fn ($query) => $query->whereHas('personal', fn ($q) => $q->where('personal.id', $personalId))
            )
            ->with(['consulta', 'caso.cliente', 'personal:id,nombres,ap_paterno'])
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get()
            ->groupBy(fn (Cita $cita) => $cita->fecha->toDateString())
            ->map(fn ($citasDelDia, $fecha) => [
                'fecha' => Carbon::parse($fecha),
                'citas' => $citasDelDia,
            ])
            ->values();

        return response()->streamDownload(
            fn () => print(Pdf::loadView('pdf.agenda-calendario', [
                'titulo' => 'Todas las citas',
                'abogado' => $abogado,
                'dias' => $dias,
            ])->output()),
            'agenda-citas.pdf'
        );
    }
}
