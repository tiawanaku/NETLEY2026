<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1a2537; }
        .header { text-align: center; margin-bottom: 18px; }
        .header h1 { margin: 0; font-size: 20px; letter-spacing: 2px; }
        .header p { margin: 2px 0; color: #666; }
        .caso { margin-bottom: 12px; page-break-inside: avoid; border: 1px solid #e5e7eb; }
        .caso-titulo { font-size: 12px; font-weight: bold; background: #1a2537; color: #fff; padding: 6px 10px; }
        .caso-titulo span { font-weight: normal; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 10px; color: #6b7280; padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .hecho { color: #15803d; font-weight: bold; }
        .vencido { color: #b91c1c; }
        .vacio { padding: 8px 10px; color: #9ca3af; }
        .firma { margin-top: 36px; text-align: center; }
        .firma .linea { border-top: 1px solid #1a2537; width: 220px; margin: 0 auto 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NETLEY</h1>
        <p>{{ $titulo }}</p>
        <p>{{ ucfirst($fecha->translatedFormat('l d \d\e F, Y')) }}{{ $responsable ? ' — '.$responsable : '' }}</p>
    </div>

    @forelse ($casos as $caso)
        @php $lista = $actuados->get($caso->id, collect()); @endphp
        <div class="caso">
            <div class="caso-titulo">
                ID {{ $caso->cliente_id }} — {{ $caso->cliente?->nombre_completo ?? 'Sin cliente' }}
                <span>· Caso N° {{ $caso->id }} · {{ $caso->materia_legal }} · {{ $caso->delito?->delito ?? $caso->delito_texto ?? '' }}</span>
            </div>
            @if ($lista->isEmpty())
                <div class="vacio">{{ $modo === 'informe' ? 'Sin actuados pendientes ni realizados hoy.' : 'Sin actuados a realizar.' }}</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th style="width:18px;">N°</th>
                            <th style="width:90px;">Actuado</th>
                            <th>Detalle</th>
                            <th style="width:90px;">Fecha / hora</th>
                            <th style="width:40px;">Alarma</th>
                            @if ($modo === 'informe')<th style="width:60px;">Concluido</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lista as $i => $a)
                            <tr class="{{ $a->vencido ? 'vencido' : '' }}">
                                <td>{{ $i + 1 }}.</td>
                                <td>{{ $a->tipo }}</td>
                                <td>{{ $a->detalle }}</td>
                                <td>{{ $a->cuando ?? '—' }}</td>
                                <td>{{ $a->alarma ? 'Sí' : '' }}</td>
                                @if ($modo === 'informe')<td class="{{ $a->concluido ? 'hecho' : '' }}">{{ $a->concluido ? 'Sí' : 'No' }}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <p class="vacio">No hay casos para mostrar.</p>
    @endforelse

    @if ($responsable)
        <div class="firma">
            <div class="linea"></div>
            {{ $responsable }}
        </div>
    @endif
</body>
</html>
