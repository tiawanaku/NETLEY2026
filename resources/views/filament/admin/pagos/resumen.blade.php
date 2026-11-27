{{--
    Pagos › Resumen de pagos (ver ListPagos): por gestión, por mes y por
    abogado de la gestión elegida. Un clic filtra el detalle de abajo.
--}}
@php
    $bs = fn ($v) => 'Bs. '.number_format((float) $v, 0, '.', '.');
    $gestiones = $this->gestiones;
    $meses = $this->meses;
    $abogados = $this->abogados;
    $totalGestion = (float) ($gestiones->get($this->gestion)?->total ?? 0);
    $fAnio = $this->filtroActivo('anio');
    $fMes = $this->filtroActivo('mes');
    $fPersonal = $this->filtroActivo('personal');
    $mesActual = (int) now()->year === (int) $this->gestion ? (int) now()->month : null;
@endphp

<div class="netley-res">
    {{-- Por gestión --}}
    <section class="netley-res-card">
        <header>
            <h3>Por gestión</h3>
            @if ($fAnio || $fMes || $fPersonal)
                <button type="button" class="netley-res-link" wire:click="verTodo">Ver todos los pagos</button>
            @endif
        </header>
        <table>
            <thead><tr><th>Gestión</th><th>Pagos</th><th>Total</th></tr></thead>
            <tbody>
                @forelse ($gestiones as $anio => $f)
                    <tr wire:key="res-gestion-{{ $anio }}" wire:click="verGestion({{ $anio }})"
                        @class(['netley-res-fila', 'netley-res-fila--elegida' => (int) $anio === (int) $this->gestion])>
                        <td><strong>{{ $anio }}</strong></td>
                        <td>{{ $f->n }}</td>
                        <td class="netley-res-monto">{{ $bs($f->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="netley-res-vacio">Sin pagos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- Por mes de la gestión --}}
    <section class="netley-res-card">
        <header><h3>Por mes · {{ $this->gestion }}</h3></header>
        <table>
            <thead><tr><th>Mes</th><th>Pagos</th><th>Total</th></tr></thead>
            <tbody>
                @foreach (\App\Filament\Admin\Resources\Pagos\Pages\ListPagos::MESES as $n => $nombre)
                    @php $f = $meses->get($n); @endphp
                    <tr wire:key="res-mes-{{ $this->gestion }}-{{ $n }}"
                        @if ($f) wire:click="verMes({{ $n }})" @endif
                        @class([
                            'netley-res-fila' => (bool) $f,
                            'netley-res-fila--vacia' => ! $f,
                            'netley-res-fila--elegida' => $f && (int) $fMes === $n && (int) $fAnio === (int) $this->gestion,
                            'netley-res-fila--actual' => $mesActual === $n,
                        ])>
                        <td>{{ $nombre }}</td>
                        <td>{{ $f->n ?? '—' }}</td>
                        <td class="netley-res-monto">{{ $f ? $bs($f->total) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td>Total {{ $this->gestion }}</td><td>{{ $meses->sum('n') }}</td><td class="netley-res-monto">{{ $bs($totalGestion) }}</td></tr></tfoot>
        </table>
    </section>

    {{-- Por abogado en la gestión --}}
    <section class="netley-res-card">
        <header><h3>Por abogado · {{ $this->gestion }}</h3></header>
        <table>
            <thead><tr><th>Abogado</th><th>Pagos</th><th>Total</th></tr></thead>
            <tbody>
                @forelse ($abogados as $a)
                    <tr wire:key="res-abogado-{{ $this->gestion }}-{{ $a->id ?? 'sin' }}"
                        @if ($a->id) wire:click="verAbogado({{ $a->id }})" @endif
                        @class([
                            'netley-res-fila' => (bool) $a->id,
                            'netley-res-fila--elegida' => $a->id && (int) $fPersonal === (int) $a->id,
                        ])>
                        <td>{{ $a->nombre }}</td>
                        <td>{{ $a->n }}</td>
                        <td class="netley-res-monto">{{ $bs($a->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="netley-res-vacio">Sin pagos en {{ $this->gestion }}.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="netley-res-nota">Un caso con varios abogados suma su pago a cada uno.</p>
    </section>
</div>
