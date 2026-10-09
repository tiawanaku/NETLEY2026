<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Citas\CitaResource;
use App\Models\Cita;
use App\Models\Personal;
use Barryvdh\DomPDF\Facade\Pdf;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Vista de calendario de la Agenda (complementa el listado de CitaResource):
 * cada día muestra sus citas, coloreadas según su origen (Consulta vs.
 * Cliente Ejecutivo). Soporta vista por mes, semana o día, y filtrado por
 * abogado.
 */
class AgendaCalendario extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $navigationLabel = 'Calendario';

    protected static \UnitEnum|string|null $navigationGroup = 'Agenda';

    protected static ?int $navigationSort = 51;

    protected static ?string $slug = 'agenda/calendario';

    protected string $view = 'filament.admin.pages.agenda-calendario';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('agenda') ?? false;
    }

    /** 'mes' | 'semana' | 'dia' */
    public string $vista = 'mes';

    /** Fecha ancla (Y-m-d): el mes/semana/día mostrado se calcula a partir de ella. */
    public string $fecha;

    public ?int $personalId = null;

    public function mount(): void
    {
        $this->fecha = now()->toDateString();

        $vista = request()->query('vista');
        $this->vista = in_array($vista, ['mes', 'semana', 'dia'], true) ? $vista : 'mes';

        $personalId = request()->query('personal');
        $this->personalId = filled($personalId) ? (int) $personalId : null;
    }

    public function getTitle(): string
    {
        return 'Calendario';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imprimir')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(fn () => $this->imprimir()),
            Action::make('verLista')
                ->label('Ver lista')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(fn () => CitaResource::getUrl('index')),
        ];
    }

    /**
     * Genera un PDF de la agenda tal como se está viendo en pantalla (mismo
     * período y filtro de abogado activos), con el nombre del abogado en el
     * encabezado si hay uno seleccionado.
     */
    protected function imprimir(): mixed
    {
        $abogado = $this->personalId ? Personal::find($this->personalId) : null;

        $dias = match ($this->vista) {
            'mes' => collect($this->semanas)
                ->flatten(1)
                ->filter(fn (array $d) => $d['enMes'] && $d['citas']->isNotEmpty())
                ->values(),
            'semana' => collect($this->diasSemana)
                ->filter(fn (array $d) => $d['citas']->isNotEmpty())
                ->values(),
            default => collect([[
                'fecha' => Carbon::parse($this->fecha),
                'citas' => $this->citasDelDia,
            ]]),
        };

        return response()->streamDownload(
            fn () => print(Pdf::loadView('pdf.agenda-calendario', [
                'titulo' => $this->titulo,
                'abogado' => $abogado,
                'dias' => $dias,
            ])->output()),
            'agenda-'.$this->fecha.'.pdf'
        );
    }

    public function setVista(string $vista): void
    {
        $this->vista = $vista;
    }

    public function anterior(): void
    {
        $this->mover(-1);
    }

    public function siguiente(): void
    {
        $this->mover(1);
    }

    protected function mover(int $direccion): void
    {
        $actual = Carbon::parse($this->fecha);

        $nueva = match ($this->vista) {
            'mes' => $actual->addMonthsNoOverflow($direccion),
            'semana' => $actual->addDays(7 * $direccion),
            default => $actual->addDay($direccion),
        };

        $this->fecha = $nueva->toDateString();
    }

    public function irHoy(): void
    {
        $this->fecha = now()->toDateString();
    }

    protected function citasQuery(): Builder
    {
        return Cita::query()
            ->where('anulada', false)
            ->when(
                $this->personalId,
                fn (Builder $query) => $query->whereHas(
                    'personal',
                    fn (Builder $q) => $q->where('personal.id', $this->personalId)
                )
            )
            ->with(['consulta', 'caso.cliente', 'personal:id,nombres,ap_paterno']);
    }

    /**
     * @return array<int, array<int, array{fecha: Carbon, enMes: bool, esHoy: bool, citas: Collection}>>
     */
    public function getSemanasProperty(): array
    {
        $inicioMes = Carbon::parse($this->fecha)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $inicioCalendario = $inicioMes->copy()->startOfWeek(Carbon::SUNDAY);
        $finCalendario = $finMes->copy()->endOfWeek(Carbon::SATURDAY);

        $citas = $this->citasQuery()
            ->whereBetween('fecha', [$inicioCalendario->toDateString(), $finCalendario->toDateString()])
            ->orderBy('hora')
            ->get()
            ->groupBy(fn (Cita $cita) => $cita->fecha->toDateString());

        $semanas = [];
        $cursor = $inicioCalendario->copy();

        while ($cursor->lte($finCalendario)) {
            $semana = [];

            for ($i = 0; $i < 7; $i++) {
                $clave = $cursor->toDateString();

                $semana[] = [
                    'fecha' => $cursor->copy(),
                    'enMes' => $cursor->month === $inicioMes->month,
                    'esHoy' => $cursor->isToday(),
                    'citas' => $citas->get($clave, collect()),
                ];

                $cursor->addDay();
            }

            $semanas[] = $semana;
        }

        return $semanas;
    }

    /**
     * @return array<int, array{fecha: Carbon, esHoy: bool, citas: Collection}>
     */
    public function getDiasSemanaProperty(): array
    {
        $inicio = Carbon::parse($this->fecha)->startOfWeek(Carbon::SUNDAY);
        $fin = $inicio->copy()->endOfWeek(Carbon::SATURDAY);

        $citas = $this->citasQuery()
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->orderBy('hora')
            ->get()
            ->groupBy(fn (Cita $cita) => $cita->fecha->toDateString());

        $dias = [];
        $cursor = $inicio->copy();

        for ($i = 0; $i < 7; $i++) {
            $dias[] = [
                'fecha' => $cursor->copy(),
                'esHoy' => $cursor->isToday(),
                'citas' => $citas->get($cursor->toDateString(), collect()),
            ];

            $cursor->addDay();
        }

        return $dias;
    }

    public function getCitasDelDiaProperty(): Collection
    {
        return $this->citasQuery()
            ->whereDate('fecha', $this->fecha)
            ->orderBy('hora')
            ->get();
    }

    public function getTituloProperty(): string
    {
        $actual = Carbon::parse($this->fecha);

        return match ($this->vista) {
            'mes' => ucfirst($actual->translatedFormat('F Y')),
            'semana' => $this->rangoSemanaTexto($actual),
            default => ucfirst($actual->translatedFormat('l d \d\e F, Y')),
        };
    }

    protected function rangoSemanaTexto(Carbon $actual): string
    {
        $inicio = $actual->copy()->startOfWeek(Carbon::SUNDAY);
        $fin = $inicio->copy()->endOfWeek(Carbon::SATURDAY);

        if ($inicio->month === $fin->month) {
            return $inicio->translatedFormat('d').' – '.$fin->translatedFormat('d \d\e F, Y');
        }

        return $inicio->translatedFormat('d \d\e F').' – '.$fin->translatedFormat('d \d\e F, Y');
    }

    /**
     * @return array<int, string>
     */
    public function getAbogadosProperty(): array
    {
        return Personal::query()
            ->where('profesion', 'Abogado')
            ->orderBy('nombres')
            ->get()
            ->mapWithKeys(fn (Personal $p) => [$p->id => $p->nombre_completo])
            ->all();
    }
}
