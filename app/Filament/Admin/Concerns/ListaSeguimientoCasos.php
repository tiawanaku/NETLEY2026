<?php

namespace App\Filament\Admin\Concerns;

use App\Enums\EstadoCaso;
use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Models\ActuadoCaso;
use App\Models\Caso;
use App\Support\AlcancePersonal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * Lista de casos vigentes de Seguimiento diario e Informe seguimiento
 * diario (mismo alcance que el resto del panel, ver AlcancePersonal):
 * casillas con Marcar / Desmarcar todos, y "Ver" abre el caso en Datos
 * judiciales con el formulario correspondiente ya abierto. Al guardar se
 * vuelve a esta lista para seguir con el próximo cliente.
 */
trait ListaSeguimientoCasos
{
    /** @var array<int, int|string> */
    public array $seleccionados = [];

    /** 'realizar' (Seguimiento diario) o 'informe' (Informe seguimiento diario). */
    abstract public function modo(): string;

    /** @return Collection<int, Caso> */
    public function getCasosProperty(): Collection
    {
        $ids = AlcancePersonal::idsRelevantes(auth()->user());
        $hoy = now()->toDateString();

        return Caso::query()
            ->whereNotIn('estado', EstadoCaso::cerrados())
            ->when($ids !== null, fn ($q) => $q->whereHas('personal', fn ($q2) => $q2->whereIn('personal.id', $ids)))
            ->with(['cliente', 'delito'])
            ->withCount([
                'actuados as pendientes_count' => fn ($q) => $q->where('concluido', false),
                'actuados as vencidos_count' => fn ($q) => $q->where('concluido', false)->whereDate('fecha', '<', $hoy),
                'actuados as concluidos_hoy_count' => fn ($q) => $q->where('concluido', true)->whereDate('concluido_at', $hoy),
                'seguimientos as seguimiento_hoy_count' => fn ($q) => $q->where('tipo_registro', 'seguimiento')->whereDate('fecha_seguimiento', $hoy),
                'seguimientos as informe_hoy_count' => fn ($q) => $q->where('tipo_registro', 'informe')->whereDate('fecha_seguimiento', $hoy),
            ])
            ->orderBy('cliente_id')
            ->orderBy('id')
            ->get();
    }

    public function marcarTodos(): void
    {
        $this->seleccionados = $this->casos->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function desmarcarTodos(): void
    {
        $this->seleccionados = [];
    }

    /** "Ver": Datos judiciales del caso con el formulario abierto. */
    public function urlVer(Caso $caso): string
    {
        return CasoResource::getUrl('edit', ['record' => $caso]).'?relation=2&seguimiento='.$this->modo();
    }

    public static function tipoProceso(Caso $caso): string
    {
        return $caso->delito?->delito ?? $caso->delito_texto ?? '—';
    }

    /** PDF de los casos marcados (o de todos, si no hay ninguno marcado). */
    protected function imprimir(string $titulo): mixed
    {
        $casos = $this->casos;

        if ($this->seleccionados !== []) {
            $marcados = array_map('intval', $this->seleccionados);
            $casos = $casos->filter(fn (Caso $c) => in_array($c->id, $marcados, true))->values();
        }

        $hoy = now()->toDateString();
        $actuados = ActuadoCaso::query()
            ->whereIn('caso_id', $casos->pluck('id'))
            ->where(fn ($q) => $this->modo() === 'informe'
                ? $q->where('concluido', false)->orWhereDate('concluido_at', $hoy)
                : $q->where('concluido', false))
            ->orderByRaw('fecha is null')->orderBy('fecha')->orderBy('hora')->orderBy('id')
            ->get()
            ->groupBy('caso_id');

        return response()->streamDownload(
            fn () => print (Pdf::loadView('pdf.seguimiento-diario', [
                'titulo' => $titulo,
                'modo' => $this->modo(),
                'fecha' => now(),
                'responsable' => auth()->user()?->personal?->nombre_completo ?? auth()->user()?->name,
                'casos' => $casos,
                'actuados' => $actuados,
            ])->output()),
            str($titulo)->slug().'-'.$hoy.'.pdf'
        );
    }
}
