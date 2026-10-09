<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Agenda</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1a2537; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 20px; letter-spacing: 2px; }
        .header p { margin: 2px 0; color: #666; }
        .dia { margin-bottom: 14px; }
        .dia-titulo { font-size: 13px; font-weight: bold; background: #1a2537; color: #fff; padding: 6px 10px; border-radius: 4px 4px 0 0; text-transform: capitalize; }
        table.citas { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.citas th { text-align: left; background: #f3f4f6; padding: 6px 8px; font-size: 11px; border-bottom: 1px solid #e5e7eb; }
        table.citas td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; font-size: 11px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: bold; }
        .badge-consulta { background: #dbeafe; color: #1e40af; }
        .badge-caso { background: #dcfce7; color: #166534; }
        .vacio { text-align: center; color: #9ca3af; padding: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NETLEY</h1>
        <p>Agenda — {{ $titulo }}</p>
        <p>{{ $abogado ? 'Abogado: '.$abogado->nombre_completo : 'Todos los abogados' }}</p>
    </div>

    @forelse ($dias as $dia)
        <div class="dia">
            <div class="dia-titulo">{{ $dia['fecha']->translatedFormat('l d \d\e F, Y') }}</div>
            <table class="citas">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Cliente</th>
                        <th>Origen</th>
                        <th>Abogado(s)</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dia['citas'] as $cita)
                        @php
                            $esCasoOrigin = $cita->caso_id !== null;
                            $nombre = $esCasoOrigin
                                ? ($cita->caso?->cliente?->nombre_completo ?? 'Caso #'.$cita->caso_id)
                                : ($cita->consulta?->nombre_completo ?? 'Consulta');
                            $hora = $cita->hora instanceof \Illuminate\Support\Carbon
                                ? $cita->hora->format('H:i')
                                : \Illuminate\Support\Carbon::parse((string) $cita->hora)->format('H:i');
                            $abogados = $cita->personal->map(fn ($p) => $p->nombres.' '.$p->ap_paterno)->implode(', ');
                        @endphp
                        <tr>
                            <td>{{ $hora }}</td>
                            <td>{{ $nombre }}</td>
                            <td>
                                <span class="badge {{ $esCasoOrigin ? 'badge-caso' : 'badge-consulta' }}">
                                    {{ $esCasoOrigin ? 'Cliente Ejecutivo' : 'Consulta' }}
                                </span>
                            </td>
                            <td>{{ $abogados ?: '-' }}</td>
                            <td>{{ $cita->detalle ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="vacio">No hay citas agendadas en este período.</div>
    @endforelse
</body>
</html>
