<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de Cierre de Consulta</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 13px; color: #1a2537; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 20px; letter-spacing: 2px; }
        .header p { margin: 2px 0; color: #666; }
        .seccion { margin-top: 4px; margin-bottom: 6px; font-size: 14px; font-weight: bold; color: #1a2537; border-bottom: 2px solid #1a2537; padding-bottom: 3px; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.datos td { padding: 6px 8px; border-bottom: 1px solid #e3e7ed; vertical-align: top; }
        table.datos td.label { font-weight: bold; width: 180px; color: #22325a; }
        .texto { border: 1px solid #e3e7ed; border-radius: 6px; padding: 12px; white-space: pre-wrap; margin-bottom: 18px; }
        .respuesta { border: 1px solid #e3e7ed; border-radius: 6px; padding: 12px; margin-bottom: 10px; }
        .respuesta .meta { color: #666; font-size: 11px; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NETLEY</h1>
        <p>Informe de Cierre de Consulta N° {{ $consulta->id }}</p>
    </div>

    <div class="seccion">Datos del cliente</div>
    <table class="datos">
        <tr><td class="label">Nombre</td><td>{{ $consulta->nombre_completo }}</td></tr>
        <tr><td class="label">Teléfono</td><td>{{ $consulta->telefono ?: '-' }}</td></tr>
        <tr><td class="label">WhatsApp</td><td>{{ $consulta->whatsapp ?: '-' }}</td></tr>
        <tr><td class="label">Correo</td><td>{{ $consulta->correo ?: '-' }}</td></tr>
        <tr><td class="label">País / Ciudad</td><td>{{ $consulta->pais ?: '-' }} / {{ $consulta->ciudad ?: '-' }}</td></tr>
        <tr><td class="label">Provincia</td><td>{{ $consulta->provincia ?: '-' }}</td></tr>
        <tr><td class="label">Dirección</td><td>{{ $consulta->direccion ?: '-' }}</td></tr>
        <tr><td class="label">Origen</td><td>{{ $consulta->origen ?: '-' }}</td></tr>
    </table>

    <div class="seccion">La consulta</div>
    <table class="datos">
        <tr><td class="label">Fecha de consulta</td><td>{{ optional($consulta->fecha_consulta)->format('d/m/Y H:i') }}</td></tr>
    </table>
    <div class="texto">{{ $consulta->consulta ?: '-' }}</div>

    <div class="seccion">Respuesta(s)</div>
    @forelse ($consulta->respuestas as $respuesta)
        <div class="respuesta">
            <div class="meta">
                {{ optional($respuesta->fecha_respuesta)->format('d/m/Y H:i') }}
                — Respondido por: {{ $respuesta->personal->nombre_completo ?? '-' }}
                @if ($respuesta->designacion)
                    — Materia: {{ $respuesta->designacion->getLabel() }}
                @endif
                @if ($respuesta->delito)
                    — Delito: {{ $respuesta->delito->delito }}
                @endif
            </div>
            {{ $respuesta->respuesta }}
        </div>
    @empty
        <div class="texto">Sin respuesta registrada.</div>
    @endforelse

    <div class="seccion">Datos del cierre</div>
    <table class="datos">
        <tr><td class="label">Motivo de cierre</td><td>{{ $consulta->estado?->getLabel() ?? '-' }}</td></tr>
    </table>
    <p><strong>Nota interna:</strong></p>
    <div class="texto">{{ $consulta->nota_interna ?: '-' }}</div>
</body>
</html>
