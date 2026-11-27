<x-filament-panels::page>
    @if ($this->cliente)
        {{-- Cliente elegido: su nombre y "Cambiar cliente" para volver a buscar. --}}
        <div class="netley-pago-elegido">
            <div>
                <span class="netley-pago-elegido-etiqueta">Cliente</span>
                <strong>{{ $this->cliente->nombre_completo }}</strong>
                <span class="netley-pago-elegido-datos">
                    CI {{ $this->cliente->ci }}{{ $this->cliente->telefono ? ' · Tel. '.$this->cliente->telefono : '' }}
                </span>
            </div>
            <x-filament::button color="gray" icon="heroicon-m-magnifying-glass" wire:click="cambiarCliente">
                Cambiar cliente
            </x-filament::button>
        </div>

        @forelse ($this->casos as $caso)
            {{-- Plan de pagos del caso: la misma tabla de su pestaña Pagos (historial + "Realizar pago"). --}}
            <div class="netley-pago-caso" wire:key="nuevo-pago-caso-{{ $caso->id }}">
                <div class="netley-pago-caso-titulo">
                    <a href="{{ \App\Filament\Admin\Resources\Casos\CasoResource::getUrl('edit', ['record' => $caso]) }}">Caso N° {{ $caso->id }}</a>
                    <span>{{ $caso->materia_legal }}{{ $caso->delito?->delito ? ' · '.$caso->delito->delito : '' }}</span>
                    @if ($caso->estado)
                        <span class="netley-pago-caso-estado">{{ $caso->estado->getLabel() }}</span>
                    @endif
                </div>

                @livewire(\App\Filament\Admin\Resources\Casos\RelationManagers\PlanesPagoRelationManager::class, [
                    'ownerRecord' => $caso,
                    'pageClass' => \App\Filament\Admin\Resources\Casos\Pages\EditCaso::class,
                ], key('nuevo-pago-plan-'.$caso->id))
            </div>
        @empty
            <x-filament::section>
                Este cliente no tiene casos registrados.
            </x-filament::section>
        @endforelse
    @else
        {{-- Cuadro de búsqueda: los resultados salen debajo mientras se escribe. --}}
        <div class="netley-pago-buscar">
            <label for="netley-pago-busqueda" class="netley-pago-buscar-etiqueta">Buscar cliente</label>
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input
                    id="netley-pago-busqueda"
                    type="search"
                    autofocus
                    autocomplete="off"
                    placeholder="Nombre, apellido, CI o teléfono del cliente"
                    wire:model.live.debounce.300ms="busqueda"
                />
            </x-filament::input.wrapper>
        </div>

        @if (mb_strlen(trim($busqueda)) >= 2)
            @if ($this->resultados->isNotEmpty())
                <div class="netley-pago-resultados">
                    @foreach ($this->resultados as $c)
                        <button type="button" class="netley-pago-resultado" wire:key="resultado-{{ $c->id }}" wire:click="elegirCliente({{ $c->id }})">
                            <strong>{{ $c->nombre_completo }}</strong>
                            <span>CI {{ $c->ci }}{{ $c->telefono ? ' · Tel. '.$c->telefono : '' }}{{ $c->whatsapp && $c->whatsapp !== $c->telefono ? ' · WhatsApp '.$c->whatsapp : '' }}</span>
                        </button>
                    @endforeach
                </div>
            @else
                <x-filament::section>
                    <p class="netley-pago-aviso">Ningún cliente coincide con "{{ trim($busqueda) }}".</p>
                </x-filament::section>
            @endif
        @else
            <x-filament::section>
                <p class="netley-pago-aviso">Escribe el nombre, apellido, CI o teléfono del cliente para ver su historial de pagos y cobrar la cuota que corresponde.</p>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
