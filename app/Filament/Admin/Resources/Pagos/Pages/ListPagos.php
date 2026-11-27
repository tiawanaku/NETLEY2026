<?php

namespace App\Filament\Admin\Resources\Pagos\Pages;

use App\Filament\Admin\Resources\Pagos\PagoResource;
use App\Models\Pago;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

/**
 * Pagos › Resumen de pagos: arriba, tres resúmenes — por gestión (año), por
 * mes y por abogado de la gestión elegida — y abajo el detalle de recibos.
 * Un clic en una gestión, un mes o un abogado filtra el detalle de abajo.
 * Solo cuentan los recibos vigentes (los anulados no, ver Pago).
 */
class ListPagos extends ListRecords
{
    use \App\Filament\Admin\Concerns\MigasSimples;

    protected static string $resource = PagoResource::class;

    public const MESES = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];

    /** Gestión (año) de los resúmenes por mes y por abogado. */
    public ?int $gestion = null;

    public function mount(): void
    {
        parent::mount();

        $this->gestion = $this->gestiones->keys()->contains((int) now()->year)
            ? (int) now()->year
            : ($this->gestiones->keys()->first() ?? (int) now()->year);
    }

    public function getTitle(): string
    {
        return 'Resumen de pagos';
    }

    protected function getHeaderActions(): array
    {
        return [
            // A la página Pagos › Nuevo pago (buscar cliente y pagar desde su plan).
            Action::make('nuevoPago')
                ->label('Nuevo pago')
                ->icon('heroicon-o-plus-circle')
                ->visible(fn (): bool => \App\Filament\Admin\Pages\NuevoPago::canAccess())
                ->url(fn (): string => \App\Filament\Admin\Pages\NuevoPago::getUrl()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.admin.pagos.resumen'),
            EmbeddedTable::make(),
        ]);
    }

    /** @return Collection<int, object{n: int, total: float}> año => totales */
    public function getGestionesProperty(): Collection
    {
        return Pago::query()
            ->selectRaw('YEAR(fecha_pago) as anio, COUNT(*) as n, SUM(monto) as total')
            ->whereNotNull('fecha_pago')
            ->groupByRaw('YEAR(fecha_pago)')
            ->orderByDesc('anio')
            ->get()
            ->keyBy(fn ($f) => (int) $f->anio);
    }

    /** @return Collection<int, object{n: int, total: float}> mes (1-12) => totales de la gestión */
    public function getMesesProperty(): Collection
    {
        return Pago::query()
            ->selectRaw('MONTH(fecha_pago) as mes, COUNT(*) as n, SUM(monto) as total')
            ->whereYear('fecha_pago', $this->gestion)
            ->groupByRaw('MONTH(fecha_pago)')
            ->get()
            ->keyBy(fn ($f) => (int) $f->mes);
    }

    /**
     * Por abogado del caso, en la gestión. Un caso con varios abogados
     * suma su pago a cada uno; los casos sin abogado van en "Sin abogado".
     *
     * @return Collection<int, object{id: ?int, nombre: string, n: int, total: float}>
     */
    public function getAbogadosProperty(): Collection
    {
        $conAbogado = Pago::query()
            ->join('caso_personal', 'caso_personal.caso_id', '=', 'pagos.caso_id')
            ->join('personal', 'personal.id', '=', 'caso_personal.personal_id')
            ->whereYear('pagos.fecha_pago', $this->gestion)
            ->selectRaw("personal.id as id, TRIM(CONCAT_WS(' ', personal.nombres, personal.ap_paterno, personal.ap_materno)) as nombre, COUNT(DISTINCT pagos.id) as n, SUM(pagos.monto) as total")
            ->groupBy('personal.id', 'personal.nombres', 'personal.ap_paterno', 'personal.ap_materno')
            ->orderByDesc('total')
            ->get();

        $sinAbogado = Pago::query()
            ->whereYear('fecha_pago', $this->gestion)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('caso_personal')->whereColumn('caso_personal.caso_id', 'pagos.caso_id'))
            ->selectRaw('COUNT(*) as n, SUM(monto) as total')
            ->first();

        if ($sinAbogado && (int) $sinAbogado->n > 0) {
            $conAbogado->push((object) ['id' => null, 'nombre' => 'Sin abogado asignado', 'n' => $sinAbogado->n, 'total' => $sinAbogado->total]);
        }

        return $conAbogado;
    }

    /** Clic en una gestión: la elige para los resúmenes y filtra el detalle por ese año. */
    public function verGestion(int $anio): void
    {
        $this->gestion = $anio;
        $this->filtrarDetalle(anio: $anio);
    }

    public function verMes(int $mes): void
    {
        $this->filtrarDetalle(anio: $this->gestion, mes: $mes);
    }

    public function verAbogado(int $personalId): void
    {
        $this->filtrarDetalle(anio: $this->gestion, personal: $personalId);
    }

    public function verTodo(): void
    {
        $this->filtrarDetalle();
    }

    /** Aplica año / mes / abogado a los filtros de la tabla de detalle. */
    protected function filtrarDetalle(?int $anio = null, ?int $mes = null, ?int $personal = null): void
    {
        $filtros = $this->tableFilters ?? [];
        $filtros['periodo'] = ['anio' => $anio, 'mes' => $mes];
        $filtros['personal'] = ['value' => $personal];

        $this->tableDeferredFilters = $filtros;
        $this->applyTableFilters();
    }

    /** Lo que filtra hoy el detalle (para resaltar la fila elegida). */
    public function filtroActivo(string $clave): mixed
    {
        return match ($clave) {
            'anio' => $this->tableFilters['periodo']['anio'] ?? null,
            'mes' => $this->tableFilters['periodo']['mes'] ?? null,
            'personal' => $this->tableFilters['personal']['value'] ?? null,
            default => null,
        };
    }
}
