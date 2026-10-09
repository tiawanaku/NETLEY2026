<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de Cierre de Caso</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 13px; color: #1a2537; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 20px; letter-spacing: 2px; }
        .header p { margin: 2px 0; color: #666; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.datos td { padding: 6px 8px; border-bottom: 1px solid #e3e7ed; vertical-align: top; }
        table.datos td.label { font-weight: bold; width: 180px; color: #22325a; }
        .nota { border: 1px solid #e3e7ed; border-radius: 6px; padding: 12px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NETLEY</h1>
        <p>Informe de Cierre de Caso N° {{ $informe->caso_id }}</p>
    </div>

    <table class="datos">
        <tr><td class="label">Cliente</td><td>{{ $informe->caso->cliente->nombre_completo }}</td></tr>
        <tr><td class="label">Materia</td><td>{{ $informe->caso->especialidad->getLabel() }}</td></tr>
        <tr><td class="label">Resultado</td><td>{{ $informe->resultado }}</td></tr>
        <tr><td class="label">Fecha de cierre</td><td>{{ optional($informe->fecha_cierre)->format('d/m/Y') }}</td></tr>
        <tr><td class="label">Saldo pendiente</td><td>Bs. {{ number_format($informe->saldo, 2) }}</td></tr>
        <tr><td class="label">Pérdida</td><td>Bs. {{ number_format($informe->perdida, 2) }}</td></tr>
        <tr><td class="label">Asume saldo/pérdida</td><td>{{ $informe->asume ?: '-' }}</td></tr>
        <tr><td class="label">Responsable seguimiento</td><td>{{ $informe->seguimiento_responsable ?: '-' }}</td></tr>
        <tr><td class="label">Opción de cierre</td><td>{{ $informe->opciones ?: '-' }}</td></tr>
        <tr><td class="label">Elaborado por</td><td>{{ $informe->creado_por ?: '-' }}</td></tr>
    </table>

    <p><strong>Nota interna:</strong></p>
    <div class="nota">{{ $informe->nota_netley ?: '-' }}</div>
</body>
</html>
