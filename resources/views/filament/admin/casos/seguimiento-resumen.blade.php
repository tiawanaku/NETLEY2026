@php
    /** @var array<int, string> $etapas */
    $actual = $ultimo?->etapa_proceso;
    $indice = $actual !== null ? array_search($actual, $etapas, true) : false;
    $vencido = $proximo?->proxima_fecha && $proximo->proxima_fecha->isPast() && ! $proximo->proxima_fecha->isToday();
    $hoy = $proximo?->proxima_fecha?->isToday();
    $dias = $proximo?->proxima_fecha ? (int) now()->startOfDay()->diffInDays($proximo->proxima_fecha, false) : null;
@endphp

{{-- Un encabezado propio reemplaza al de la tabla: título y botón van aquí. --}}
<div class="fi-ta-header fi-ta-header-adaptive-actions-position">
    <div>
        <h3 class="fi-ta-header-heading">Seguimiento del proceso</h3>
        <p class="fi-ta-header-description">En qué etapa está el caso, qué se hizo y qué sigue.</p>
    </div>
    @if (count($acciones))
        <div class="fi-ta-actions fi-align-start fi-wrapped">
            @foreach ($acciones as $accion)
                {{ $accion }}
            @endforeach
        </div>
    @endif
</div>

<div class="netley-seg">
    {{-- Barra de avance: etapas de la materia, la actual resaltada. --}}
    <div class="netley-seg-etapas">
        @foreach ($etapas as $i => $etapa)
            @php
                $estado = $indice === false ? 'pendiente' : ($i < $indice ? 'hecha' : ($i === $indice ? 'actual' : 'pendiente'));
            @endphp
            <div class="netley-seg-etapa netley-seg-etapa--{{ $estado }}" title="{{ $etapa }}">
                <span class="netley-seg-punto">{{ $estado === 'hecha' ? '✓' : $i + 1 }}</span>
                <span class="netley-seg-nombre">{{ $etapa }}</span>
            </div>
        @endforeach
    </div>

    @if ($actual !== null && $indice === false)
        <p class="netley-seg-nota">Etapa registrada: <strong>{{ $actual }}</strong></p>
    @endif

    <div class="netley-seg-tarjetas">
        <div class="netley-seg-tarjeta">
            <span class="netley-seg-titulo">Etapa actual</span>
            <strong>{{ $actual ?? 'Sin registrar' }}</strong>
        </div>
        <div class="netley-seg-tarjeta">
            <span class="netley-seg-titulo">Instancia vigente</span>
            <strong>{{ $instancia ?? ($ultimo?->instancia ?? 'Sin registrar') }}</strong>
        </div>
        <div class="netley-seg-tarjeta">
            <span class="netley-seg-titulo">Último movimiento</span>
            @if ($ultimo)
                <strong>{{ $ultimo->fecha_seguimiento->format('d/m/Y') }} · {{ $ultimo->tipo_actuacion ?? 'Seguimiento' }}</strong>
                <span class="netley-seg-sub">{{ $ultimo->abogado_nombre ?? '—' }} · hace {{ $ultimo->fecha_seguimiento->startOfDay()->diffForHumans(now()->startOfDay(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}</span>
            @else
                <strong>Sin seguimientos</strong>
            @endif
        </div>
        <div class="netley-seg-tarjeta {{ $vencido ? 'netley-seg-tarjeta--vencida' : ($hoy || ($dias !== null && $dias <= 3) ? 'netley-seg-tarjeta--pronto' : '') }}">
            <span class="netley-seg-titulo">Próxima actuación</span>
            @if ($proximo)
                <strong>{{ $proximo->proxima_fecha->format('d/m/Y') }} · {{ $proximo->proxima_actuacion }}</strong>
                <span class="netley-seg-sub">
                    @if ($vencido) Vencida hace {{ abs($dias) }} {{ abs($dias) === 1 ? 'día' : 'días' }}
                    @elseif ($hoy) Es hoy
                    @else Faltan {{ $dias }} {{ $dias === 1 ? 'día' : 'días' }}
                    @endif
                </span>
            @else
                <strong>Nada pendiente</strong>
            @endif
        </div>
    </div>
</div>
