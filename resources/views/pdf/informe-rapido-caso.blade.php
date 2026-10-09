<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe Rápido de Caso</title>
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
    </style>
</head>
<body>
    <div class="header">
        <h1>NETLEY</h1>
        <p>Informe Rápido de Caso N° {{ $caso->id }}</p>
    </div>

    <div class="seccion">Datos del cliente</div>
    <table class="datos">
        <tr><td class="label">Nombre</td><td>{{ $caso->cliente->nombre_completo ?? '-' }}</td></tr>
        <tr><td class="label">Carnet de identidad</td><td>{{ $caso->cliente->ci ?? '-' }}{{ $caso->cliente?->extension ? " ({$caso->cliente->extension})" : '' }}</td></tr>
        <tr><td class="label">Teléfono</td><td>{{ $caso->cliente->telefono ?? '-' }}</td></tr>
        <tr><td class="label">WhatsApp</td><td>{{ $caso->cliente->whatsapp ?? '-' }}</td></tr>
        <tr><td class="label">Correo</td><td>{{ $caso->cliente->correo ?? '-' }}</td></tr>
        <tr><td class="label">Dirección</td><td>{{ $caso->cliente->direccion ?? '-' }}</td></tr>
    </table>

    <div class="seccion">Ficha del caso</div>
    <table class="datos">
        <tr><td class="label">Materia legal</td><td>{{ $caso->materia_legal }}</td></tr>
        <tr><td class="label">Delito</td><td>{{ $caso->delito->delito ?? ($caso->delito_texto ?: '-') }}</td></tr>
        <tr><td class="label">Apersonamiento</td><td>{{ $caso->apersonamiento ?: '-' }}</td></tr>
        <tr><td class="label">Ciudad</td><td>{{ $caso->ciudad ?: '-' }}</td></tr>
        <tr><td class="label">Estado</td><td>{{ $caso->estado?->getLabel() ?? '-' }}</td></tr>
        <tr><td class="label">Abogado(s) asignado(s)</td><td>{{ $caso->personal->pluck('nombre_completo')->implode(', ') ?: '-' }}</td></tr>
    </table>
    @if ($caso->descripcion)
        <div class="texto">{{ $caso->descripcion }}</div>
    @endif

    <div class="seccion">Finanzas</div>
    <table class="datos">
        <tr><td class="label">Fecha de inicio</td><td>{{ optional($caso->fecha_inicio)->format('d/m/Y') }}</td></tr>
        <tr><td class="label">Vencimiento</td><td>{{ $caso->fecha_fin ? $caso->fecha_fin->format('d/m/Y') : '-' }}</td></tr>
        <tr><td class="label">Iguala</td><td>Bs. {{ number_format($caso->iguala, 2) }}</td></tr>
        <tr><td class="label">Pagado</td><td>Bs. {{ number_format($caso->pagado, 2) }}</td></tr>
        <tr><td class="label">Saldo</td><td>Bs. {{ number_format($caso->saldo, 2) }}</td></tr>
    </table>
</body>
</html>
