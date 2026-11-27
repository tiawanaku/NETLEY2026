{{--
    Barra de paginación compacta (ver components/pagination/index.blade.php):
    « ‹   Registros 1 al 10 de 45 · Página [ 1 ] de 5 · [10 por página]   › »
    Los botones de los extremos siempre se ven (deshabilitados en la primera
    o la última página), así la barra no cambia de forma al navegar.
--}}
@php
    use Illuminate\Support\Number;

    $pagina = $paginator->currentPage();
    $ultima = max($paginator->lastPage(), 1);
    $nombre = $paginator->getPageName();
    $enPrimera = $paginator->onFirstPage();
    $enUltima = ! $paginator->hasMorePages();
    $ir = fn (int $p) => "gotoPage({$p}, '{$nombre}')";
@endphp

<nav
    aria-label="{{ __('filament::components/pagination.label') }}"
    {{ $attributes->class(['fi-pagination', 'netley-pag']) }}
>
    <div class="netley-pag-lado">
        <x-filament::icon-button
            icon="heroicon-m-chevron-double-left"
            color="gray"
            label="Primera página"
            tooltip="Primera página"
            :disabled="$enPrimera"
            :wire:click="$enPrimera ? null : $ir(1)"
            :wire:key="$this->getId() . '.netley-pag.primera'"
        />
        <x-filament::icon-button
            icon="heroicon-m-chevron-left"
            color="gray"
            label="Página anterior"
            tooltip="Página anterior"
            :disabled="$enPrimera"
            :wire:click="$enPrimera ? null : $ir($pagina - 1)"
            :wire:key="$this->getId() . '.netley-pag.anterior'"
        />
    </div>

    <div class="netley-pag-centro">
        <span class="netley-pag-texto">
            @if ($paginator->total() > 0)
                Registros {{ Number::format($paginator->firstItem()) }} al {{ Number::format($paginator->lastItem()) }} de {{ Number::format($paginator->total()) }}
            @else
                Sin registros
            @endif
        </span>

        @if ($ultima > 1)
            <label class="netley-pag-pagina">
                <span>Página</span>
                <input
                    type="number"
                    min="1"
                    max="{{ $ultima }}"
                    value="{{ $pagina }}"
                    wire:key="{{ $this->getId() }}.netley-pag.input.{{ $pagina }}"
                    aria-label="Ir a la página"
                    class="netley-pag-input"
                    x-on:keydown.enter.prevent="$event.target.blur()"
                    x-on:change="
                        let p = Math.min({{ $ultima }}, Math.max(1, parseInt($event.target.value) || 1));
                        $event.target.value = p;
                        if (p !== {{ $pagina }}) $wire.gotoPage(p, @js($nombre));
                    "
                />
                <span>de {{ Number::format($ultima) }}</span>
            </label>
        @endif

        @if (count($pageOptions) > 1)
            <label class="netley-pag-por-pagina">
                <x-filament::input.wrapper>
                    <x-filament::input.select :wire:model.live="$currentPageOptionProperty">
                        @foreach ($pageOptions as $option)
                            <option value="{{ $option }}">
                                {{ $option === 'all' ? 'Todos' : $option.' por página' }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                <span class="fi-sr-only">Registros por página</span>
            </label>
        @endif
    </div>

    <div class="netley-pag-lado">
        <x-filament::icon-button
            icon="heroicon-m-chevron-right"
            color="gray"
            label="Página siguiente"
            tooltip="Página siguiente"
            :disabled="$enUltima"
            :wire:click="$enUltima ? null : $ir($pagina + 1)"
            :wire:key="$this->getId() . '.netley-pag.siguiente'"
        />
        <x-filament::icon-button
            icon="heroicon-m-chevron-double-right"
            color="gray"
            label="Última página"
            tooltip="Última página"
            :disabled="$enUltima"
            :wire:click="$enUltima ? null : $ir($ultima)"
            :wire:key="$this->getId() . '.netley-pag.ultima'"
        />
    </div>
</nav>
