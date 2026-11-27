<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\MigasDeNavegacion;
use App\Models\Caso;
use App\Models\Cliente;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Pagos › Nuevo pago: en un cuadro de búsqueda se escribe nombre,
 * apellido, CI o teléfono; los clientes que coinciden salen debajo y, al
 * hacer clic en uno, abajo
 * aparece, por cada uno de sus casos, el plan de pagos con su historial
 * (cuotas pagadas, recibos, anulados) y el botón "Pagar" en la cuota que
 * toca — la misma tabla de la pestaña Pagos del caso
 * (PlanesPagoRelationManager), así se cobra igual desde los dos lados.
 * Con ?cliente=ID abre ya con ese cliente elegido.
 */
class NuevoPago extends Page
{
    use MigasDeNavegacion;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static ?string $navigationLabel = 'Nuevo pago';

    protected static \UnitEnum|string|null $navigationGroup = 'Pagos';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'pagos/nuevo-pago';

    protected string $view = 'filament.admin.pages.nuevo-pago';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('pagos') ?? false;
    }

    public function getTitle(): string
    {
        return 'Nuevo pago';
    }

    protected function migas(): array
    {
        return [['Pagos'], ['Nuevo pago']];
    }

    /** Lo escrito en el cuadro de búsqueda (los resultados salen debajo al escribir). */
    public string $busqueda = '';

    /** Cliente elegido; va en la URL (?cliente=ID) para poder volver o compartir. */
    #[Url(as: 'cliente')]
    public ?int $clienteId = null;

    /** @return Collection<int, Cliente> */
    public function getResultadosProperty(): Collection
    {
        return mb_strlen(trim($this->busqueda)) >= 2 ? self::buscar($this->busqueda) : collect();
    }

    public function elegirCliente(int $id): void
    {
        $this->clienteId = $id;
        $this->busqueda = '';
    }

    public function cambiarCliente(): void
    {
        $this->clienteId = null;
        $this->busqueda = '';
    }

    /**
     * Un solo cuadro para todo: cada palabra escrita tiene que aparecer en
     * el nombre, algún apellido, el CI, el teléfono o el WhatsApp (en
     * cualquier orden: "torrez mery", "mery 3325599"…). Un número con
     * +591, espacios o guiones se busca solo por sus dígitos, sin el 591.
     *
     * @return Collection<int, Cliente>
     */
    public static function buscar(string $search): Collection
    {
        $search = trim($search);

        if ($search === '') {
            return collect();
        }

        // Solo número (teléfono / CI escrito con formato): una sola "palabra".
        if (preg_match('/^[\d\s+\-().]+$/', $search)) {
            $digitos = preg_replace('/\D/', '', $search);
            $palabras = [strlen($digitos) > 8 ? preg_replace('/^591/', '', $digitos) : $digitos];
        } else {
            $palabras = preg_split('/\s+/', $search);
        }

        $query = Cliente::query()->whereHas('casos');

        foreach (array_filter($palabras, fn ($p) => $p !== '') as $palabra) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $palabra).'%';
            $query->where(fn ($q) => $q
                ->where('nombres', 'like', $like)
                ->orWhere('ap_paterno', 'like', $like)
                ->orWhere('ap_materno', 'like', $like)
                ->orWhere('ci', 'like', $like)
                ->orWhere('telefono', 'like', $like)
                ->orWhere('whatsapp', 'like', $like));
        }

        return $query->orderBy('nombres')->orderBy('ap_paterno')->limit(50)->get();
    }

    public function getClienteProperty(): ?Cliente
    {
        return $this->clienteId ? Cliente::find($this->clienteId) : null;
    }

    /**
     * Casos del cliente: primero los que tienen saldo por cobrar, luego el
     * resto (cerrados o ya pagados), del más nuevo al más viejo.
     *
     * @return Collection<int, Caso>
     */
    public function getCasosProperty(): Collection
    {
        if (! $this->cliente) {
            return collect();
        }

        return $this->cliente->casos()
            ->orderByRaw('saldo > 0 desc')
            ->orderByDesc('id')
            ->get();
    }
}
