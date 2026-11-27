@php
    /** @var array<int, string> $etapas */
    $actual = $ultimo?->etapa_proceso;
    $indice = $actual !== null ? array_search($actual, $etapas, true) : false;
    // $pendientes: actuados no concluidos (ActuadoCaso), el más próximo primero.
    // Alarma: el próximo actuado pendiente CON alarma (fecha más cercana o más atrasada).
    $proximo = $pendientes->first(fn ($a) => $a->alarma && $a->fecha) ?? $pendientes->first(fn ($a) => $a->alarma);
    $vencido = $proximo?->fecha && $proximo->fecha->isPast() && ! $proximo->fecha->isToday();
    $hoy = $proximo?->fecha?->isToday();
    $dias = $proximo?->fecha ? (int) now()->startOfDay()->diffInDays($proximo->fecha, false) : null;
    $vencidas = $pendientes->filter(fn ($a) => $a->vencido)->count();
    $fs = fn ($n) => $n === null ? null : number_format((int) $n, 0, '.', '.');
@endphp

{{-- Un encabezado propio reemplaza al de la tabla: título y botón van aquí. --}}
<div class="fi-ta-header fi-ta-header-adaptive-actions-position">
    <div>
        <h3 class="fi-ta-header-heading">Seguimiento del proceso</h3>
        <p class="fi-ta-header-description">Actuados a realizar y actuados realizados del caso.</p>
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

    {{-- Fojas: campo "Fojas (Fs.)" de la fiscalía / el juzgado vigentes (Datos del proceso o sus pestañas). --}}
    <div class="netley-seg-tarjetas">
        <div class="netley-seg-tarjeta {{ $vencidas ? 'netley-seg-tarjeta--vencida' : '' }}">
            <span class="netley-seg-titulo">Informes pendientes</span>
            <strong class="netley-seg-numero">{{ $pendientes->count() }}</strong>
            <span class="netley-seg-sub">
                @if ($pendientes->isEmpty())
                    Nada por hacer
                @elseif ($vencidas)
                    {{ $vencidas }} {{ $vencidas === 1 ? 'vencida' : 'vencidas' }} · regístralas en "Actuados realizados"
                @else
                    {{ $pendientes->count() === 1 ? 'Actuado por realizar' : 'Actuados por realizar' }}
                @endif
            </span>
        </div>
        <div class="netley-seg-tarjeta">
            <span class="netley-seg-titulo">Fs. Ministerio Público</span>
            @if (! $esPenal && ! $fiscalia)
                <strong>No aplica</strong>
                <span class="netley-seg-sub">Sin Ministerio Público en esta materia</span>
            @elseif ($fiscalia?->fojas !== null)
                <strong class="netley-seg-numero">{{ $fs($fiscalia->fojas) }} <small>fs.</small></strong>
                <span class="netley-seg-sub">{{ $fiscalia->fiscalia_num ?: 'Fiscalía vigente' }}</span>
            @else
                <strong>Sin registrar</strong>
                <span class="netley-seg-sub">{{ $fiscalia ? ($fiscalia->fiscalia_num ?: 'Fiscalía vigente').' · falta cargar las fojas' : 'Sin fiscalía vigente' }}</span>
            @endif
        </div>
        <div class="netley-seg-tarjeta">
            <span class="netley-seg-titulo">Fs. Juzgado</span>
            @if ($juzgado?->fojas !== null)
                <strong class="netley-seg-numero">{{ $fs($juzgado->fojas) }} <small>fs.</small></strong>
                <span class="netley-seg-sub">{{ $juzgado->juzgado_num ?: 'Juzgado vigente' }}</span>
            @else
                <strong>Sin registrar</strong>
                <span class="netley-seg-sub">{{ $juzgado ? ($juzgado->juzgado_num ?: 'Juzgado vigente').' · falta cargar las fojas' : 'Sin juzgado vigente' }}</span>
            @endif
        </div>
        <div class="netley-seg-tarjeta {{ $vencido ? 'netley-seg-tarjeta--vencida' : ($proximo && ($hoy || ($dias !== null && $dias <= 3)) ? 'netley-seg-tarjeta--pronto' : '') }}">
            <span class="netley-seg-titulo">Alarma</span>
            @if ($proximo)
                <strong>{{ $proximo->cuando ?? 'Sin fecha' }} · {{ $proximo->tipo }}{{ $proximo->detalle ? ': '.\Illuminate\Support\Str::limit($proximo->detalle, 60) : '' }}</strong>
                <span class="netley-seg-sub">
                    @if ($vencido) Vencida hace {{ abs($dias) }} {{ abs($dias) === 1 ? 'día' : 'días' }}
                    @elseif ($hoy) Es hoy
                    @elseif ($dias === null) Sin fecha
                    @else Faltan {{ $dias }} {{ $dias === 1 ? 'día' : 'días' }}
                    @endif
                </span>
            @else
                <strong>Sin alarmas</strong>
                <span class="netley-seg-sub">Ningún actuado pendiente con alarma</span>
            @endif
        </div>
    </div>
</div>
