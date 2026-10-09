<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de Cita</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 13px; color: #1a2537; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 20px; letter-spacing: 2px; }
        .header p { margin: 2px 0; color: #666; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.datos td { padding: 6px 8px; border-bottom: 1px solid #e3e7ed; vertical-align: top; }
        table.datos td.label { font-weight: bold; width: 160px; color: #22325a; }
        .redaccion { border: 1px solid #e3e7ed; border-radius: 6px; padding: 12px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NETLEY</h1>
        <p>Informe de Cita — {{ $cita->fecha->format('d/m/Y') }} {{ $cita->hora }}</p>
    </div>

    <table class="datos">
        <tr><td class="label">Nombres</td><td>{{ $informe->nombres }} {{ $informe->apellidos }}</td></tr>
        <tr><td class="label">Teléfono</td><td>{{ $informe->telefono }}</td></tr>
        <tr><td class="label">Forma de ingreso</td><td>{{ $informe->forma_ingreso ?: '-' }}</td></tr>
        <tr><td class="label">Colegio (si aplica)</td><td>{{ $informe->nombre_colegio ?: '-' }}</td></tr>
        <tr><td class="label">Responsable</td><td>{{ $informe->responsable ?: '-' }}</td></tr>
        <tr><td class="label">Acciones</td><td>{{ $informe->acciones ?: '-' }}</td></tr>
        <tr><td class="label">Anticipo</td><td>Bs. {{ number_format($informe->anticipo, 2) }}</td></tr>
        <tr><td class="label">Iguala</td><td>Bs. {{ number_format($informe->iguala, 2) }}</td></tr>
        <tr><td class="label">Detalle</td><td>{{ $informe->detalle ?: '-' }}</td></tr>
    </table>

    <p><strong>Redacción:</strong></p>
    <div class="redaccion">{{ $informe->redaccion ?: '-' }}</div>
</body>
</html>
