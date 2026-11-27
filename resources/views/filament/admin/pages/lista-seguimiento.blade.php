{{--
    Seguimiento diario / Informe seguimiento diario (ver Concerns\ListaSeguimientoCasos).
    "Ver" abre Datos judiciales del caso con el formulario ya abierto.
--}}
@php
    $informe = $this->modo() === 'informe';
    $casos = $this->casos;
@endphp

<x-filament-panels::page>
    <div class="netley-lseg-barra">
        <p>
            @if ($informe)
                Registra en cada cliente los <strong>actuados realizados</strong>. Lo que no se concluya sigue pendiente mañana.
            @else
                Registra en cada cliente los <strong>actuados a realizar</strong>. Al guardar vuelves aquí para seguir con el próximo.
            @endif
        </p>
        <div class="netley-lseg-botones">
            <x-filament::button color="gray" size="sm" wire:click="marcarTodos">Marcar todos</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="desmarcarTodos">Desmarcar todos</x-filament::button>
        </div>
    </div>

    <div class="netley-lseg-tabla">
        @if ($casos->isEmpty())
            <p class="netley-lseg-vacio">No tienes casos vigentes asignados.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th class="netley-lseg-check"></th>
                        <th>ID Cliente</th>
                        <th>Nombre</th>
                        <th>Materia legal</th>
                        <th>Tipo de proceso</th>
                        <th>{{ $informe ? 'Actuados realizados' : 'Actuados a realizar' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($casos as $caso)
                        @php
                            $hecho = $informe ? $caso->informe_hoy_count > 0 : $caso->seguimiento_hoy_count > 0;
                        @endphp
                        <tr wire:key="lseg-{{ $caso->id }}" @class(['netley-lseg-hecho' => $hecho])>
                            <td class="netley-lseg-check">
                                <input type="checkbox" wire:model.live="seleccionados" value="{{ $caso->id }}">
                            </td>
                            <td class="netley-lseg-id">ID {{ $caso->cliente_id }}<span>Caso N° {{ $caso->id }}</span></td>
                            <td>{{ $caso->cliente?->nombre_completo ?? 'Sin cliente' }}</td>
                            <td>{{ $caso->materia_legal }}</td>
                            <td>{{ $this::tipoProceso($caso) }}</td>
                            <td>
                                <div class="netley-lseg-ver">
                                    <x-filament::button size="sm" :color="$informe ? 'success' : 'primary'" tag="a" :href="$this->urlVer($caso)" icon="heroicon-m-eye">
                                        Ver
                                    </x-filament::button>
                                    <span class="netley-lseg-info">
                                        @if ($informe)
                                            {{ $caso->concluidos_hoy_count }} {{ $caso->concluidos_hoy_count === 1 ? 'realizado' : 'realizados' }} hoy
                                            · {{ $caso->pendientes_count }} {{ $caso->pendientes_count === 1 ? 'pendiente' : 'pendientes' }}
                                        @else
                                            {{ $caso->pendientes_count }} {{ $caso->pendientes_count === 1 ? 'pendiente' : 'pendientes' }}
                                        @endif
                                        @if ($caso->vencidos_count)
                                            <b class="netley-lseg-vencido">· {{ $caso->vencidos_count }} {{ $caso->vencidos_count === 1 ? 'vencido' : 'vencidos' }}</b>
                                        @endif
                                        @if ($hecho)
                                            <b class="netley-lseg-ok">✓ {{ $informe ? 'Informe hoy' : 'Registrado hoy' }}</b>
                                        @endif
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if (count($seleccionados))
        <p class="netley-lseg-nota">{{ count($seleccionados) }} {{ count($seleccionados) === 1 ? 'cliente marcado' : 'clientes marcados' }}: "Imprimir" saldrá solo con {{ count($seleccionados) === 1 ? 'ese' : 'esos' }}.</p>
    @endif
</x-filament-panels::page>
