<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Historial de Pagos</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 13px; color: #1a2537; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 20px; letter-spacing: 2px; }
        .header p { margin: 2px 0; color: #666; }
        .seccion { margin-top: 4px; margin-bottom: 6px; font-size: 14px; font-weight: bold; color: #1a2537; border-bottom: 2px solid #1a2537; padding-bottom: 3px; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.datos td { padding: 6px 8px; border-bottom: 1px solid #e3e7ed; vertical-align: top; }
        table.datos td.label { font-weight: bold; width: 180px; color: #22325a; }
        table.historial { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.historial th { text-align: left; background: #1a2537; color: #fff; padding: 8px; font-size: 12px; }
        table.historial td { padding: 8px; border-bottom: 1px solid #e3e7ed; }
        table.historial tfoot td { font-weight: bold; border-top: 2px solid #1a2537; border-bottom: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NETLEY</h1>
        <p>Historial de Pagos — Caso N° {{ $caso->id }}</p>
    </div>

    <div class="seccion">Datos del cliente</div>
    <table class="datos">
        <tr><td class="label">Nombre</td><td>{{ $caso->cliente->nombre_completo ?? '-' }}</td></tr>
        <tr><td class="label">Carnet de identidad</td><td>{{ $caso->cliente->ci ?? '-' }}</td></tr>
        <tr><td class="label">Materia legal</td><td>{{ $caso->materia_legal }}</td></tr>
        <tr><td class="label">Iguala</td><td>Bs. {{ number_format($caso->iguala, 2) }}</td></tr>
        <tr><td class="label">Saldo actual</td><td>Bs. {{ number_format($caso->saldo, 2) }}</td></tr>
    </table>

    <div class="seccion">Pagos registrados</div>
    <table class="historial">
        <thead>
            <tr>
                <th>Recibo #</th>
                <th>Cuota #</th>
                <th>Fecha</th>
                <th>Sucursal</th>
                <th>Registrado por</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pagos as $pago)
                <tr>
                    <td>{{ str_pad($pago->nro_recibo, 6, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $pago->nro_cuota }}</td>
                    <td>{{ $pago->fecha_pago->format('d/m/Y') }}</td>
                    <td>{{ $pago->sucursal }}</td>
                    <td>{{ $pago->registrado_por ?? '-' }}</td>
                    <td>Bs. {{ number_format($pago->monto, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Sin pagos registrados.</td></tr>
            @endforelse
        </tbody>
        @if ($pagos->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="5">Total pagado</td>
                    <td>Bs. {{ number_format($pagos->sum('monto'), 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
