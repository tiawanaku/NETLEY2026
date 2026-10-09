<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe diario</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1a2537; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 20px; letter-spacing: 2px; }
        .header p { margin: 2px 0; color: #666; }
        .caso { margin-bottom: 16px; page-break-inside: avoid; border: 1px solid #e5e7eb; border-radius: 4px; }
        .caso-titulo {
            font-size: 13px; font-weight: bold; background: #1a2537; color: #fff;
            padding: 8px 12px; border-radius: 4px 4px 0 0;
        }
        .caso-cuerpo { padding: 10px 12px; }
        .caso-meta { font-size: 11px; color: #666; margin-bottom: 6px; }
        .caso-nota { font-size: 12px; line-height: 1.5; }
        .vacio { text-align: center; color: #9ca3af; padding: 30px; }
        .firma { margin-top: 40px; text-align: center; }
        .firma .linea { border-top: 1px solid #1a2537; width: 220px; margin: 0 auto 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NETLEY</h1>
        <p>Informe diario de seguimiento</p>
        <p>{{ ucfirst($fecha->translatedFormat('l d \d\e F, Y')) }} — {{ $responsable ?? 'Sin responsable' }}</p>
    </div>

    @forelse ($seguimientos as $seguimiento)
        <div class="caso">
            <div class="caso-titulo">
                Caso #{{ $seguimiento->caso_id }} — {{ $seguimiento->caso?->cliente?->nombre_completo ?? 'Sin cliente' }}
            </div>
            <div class="caso-cuerpo">
                <div class="caso-meta">
                    {{ $seguimiento->caso?->especialidad?->getLabel() ?? '' }}
                    @if ($seguimiento->caso?->estado)
                        &middot; {{ $seguimiento->caso->estado->getLabel() }}
                    @endif
                </div>
                <div class="caso-nota">{{ $seguimiento->observaciones }}</div>
            </div>
        </div>
    @empty
        <div class="vacio">Todavía no se registraron seguimientos diarios hoy.</div>
    @endforelse

    <div class="firma">
        <div class="linea"></div>
        {{ $responsable ?? '' }}
    </div>
</body>
</html>
