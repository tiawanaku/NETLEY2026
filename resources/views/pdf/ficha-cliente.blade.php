@php
    /** @var \App\Models\Cliente $cliente */
    $azul = '#1a2537';
    $v = fn ($valor) => filled($valor) ? $valor : '—';
    $ubicacion = \App\Filament\Admin\Support\CamposDomicilio::ubicacionValida($cliente->ubicacion);
    $mapa = $ubicacion ? \App\Support\MapaEstatico::base64($ubicacion['lat'], $ubicacion['lng']) : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Datos del cliente {{ $cliente->nombre_completo }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 14mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5px; color: #1f2937; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; }

        .encabezado td { vertical-align: middle; }
        .titulo { font-size: 17px; font-weight: bold; color: {{ $azul }}; margin: 0; text-align: right; }
        .subtitulo { font-size: 10px; color: #6b7280; text-align: right; margin-top: 3px; }
        .badge { display: inline-block; background: {{ $azul }}; color: #fff; font-weight: bold; font-size: 11px; padding: 3px 10px; border-radius: 4px; margin-top: 4px; }

        .seccion { margin: 14px 0 6px; font-size: 11.5px; font-weight: bold; color: {{ $azul }}; border-bottom: 2px solid {{ $azul }}; padding-bottom: 3px; text-transform: uppercase; letter-spacing: .5px; }

        table.datos td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; }
        table.datos td.et { font-weight: bold; color: #22325a; width: 17%; white-space: nowrap; }
        table.datos td.va { width: 33%; }

        .texto { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; white-space: pre-wrap; }
        .mapa { border: 1px solid #d1d5db; border-radius: 4px; width: 100%; }
        .vacio { color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td style="width: 45%;">
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/netley-logo-pdf.png'))) }}" style="width: 150px;" alt="NETLEY">
            </td>
            <td style="width: 55%;">
                <p class="titulo">Datos del cliente</p>
                <div style="text-align: right;"><span class="badge">Cliente N° {{ $cliente->nro_cliente ?? $cliente->id }}</span></div>
                <div class="subtitulo">Emitido el {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="seccion">Datos personales</div>
    <table class="datos">
        <tr>
            <td class="et">Nombre</td><td class="va">{{ $v($cliente->nombre_completo) }}</td>
            <td class="et">C.I.</td><td class="va">{{ $v($cliente->ci) }}{{ $cliente->extension ? " ({$cliente->extension})" : '' }}</td>
        </tr>
        <tr>
            <td class="et">Fecha de nacimiento</td><td class="va">{{ $cliente->fecha_nacimiento?->format('d/m/Y') ?? '—' }}</td>
            <td class="et">Sucursal</td><td class="va">{{ $v($cliente->sucursal) }}</td>
        </tr>
        <tr>
            <td class="et">Teléfono</td><td class="va">{{ \App\Support\Telefono::formatear($cliente->telefono) ?: '—' }}</td>
            <td class="et">WhatsApp</td><td class="va">{{ \App\Support\Telefono::formatear($cliente->whatsapp) ?: '—' }}</td>
        </tr>
        <tr>
            <td class="et">Correo</td><td class="va">{{ $v($cliente->correo) }}</td>
            <td class="et">Casos</td><td class="va">{{ $cliente->casos()->count() }}</td>
        </tr>
    </table>

    <div class="seccion">Domicilio</div>
    <table class="datos">
        <tr>
            <td class="et">País</td><td class="va">{{ $v($cliente->pais) }}</td>
            <td class="et">Provincia</td><td class="va">{{ $v($cliente->provincia) }}</td>
        </tr>
        <tr>
            <td class="et">Ciudad</td><td class="va">{{ $v($cliente->ciudad) }}</td>
            <td class="et">Zona</td><td class="va">{{ $v($cliente->zona) }}</td>
        </tr>
        <tr>
            <td class="et">Calle(s)</td><td class="va">{{ $v($cliente->calles) }}</td>
            <td class="et">N° de domicilio</td><td class="va">{{ $v($cliente->numero_domicilio) }}</td>
        </tr>
        @if (filled($cliente->direccion))
            <tr>
                <td class="et">Dirección</td><td class="va" colspan="3">{{ $cliente->direccion }}</td>
            </tr>
        @endif
    </table>

    @if (filled($cliente->indicaciones_domicilio))
        <div style="margin-top: 8px; font-weight: bold; color: #22325a;">Indicaciones</div>
        <div class="texto">{{ $cliente->indicaciones_domicilio }}</div>
    @endif

    <div class="seccion">Ubicación del domicilio</div>
    @if (! $ubicacion)
        <p class="vacio" style="margin: 2px 0;">No se marcó la ubicación en el mapa.</p>
    @else
        @if ($mapa)
            <img class="mapa" src="data:image/png;base64,{{ $mapa }}" alt="Mapa del domicilio">
        @else
            <p class="vacio" style="margin: 2px 0;">No se pudo cargar el mapa (sin conexión a internet).</p>
        @endif
        <p style="margin: 6px 0 0;">
            <strong style="color: #22325a;">Coordenadas:</strong>
            {{ number_format($ubicacion['lat'], 6, '.', '') }}, {{ number_format($ubicacion['lng'], 6, '.', '') }}
            &nbsp;·&nbsp; https://www.google.com/maps?q={{ $ubicacion['lat'] }},{{ $ubicacion['lng'] }}
        </p>
    @endif
</body>
</html>
