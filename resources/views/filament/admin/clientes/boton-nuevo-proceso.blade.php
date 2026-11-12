{{-- Al final de la barra de pestañas de la ficha del cliente (ver EditCliente). --}}
@if (\App\Filament\Admin\Resources\Casos\CasoResource::canCreate())
    <div style="display:flex;align-items:center;margin-inline-start:.5rem;">
        <x-filament::button size="sm" icon="heroicon-o-plus" wire:click="mountAction('anadirCaso')">
            Nuevo proceso
        </x-filament::button>
    </div>
@endif
