<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Plan de Pagos</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 13px; color: #1a2537; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 20px; letter-spacing: 2px; }
        .header p { margin: 2px 0; color: #666; }
        .seccion { margin-top: 4px; margin-bottom: 6px; font-size: 14px; font-weight: bold; color: #1a2537; border-bottom: 2px solid #1a2537; padding-bottom: 3px; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.datos td { padding: 6px 8px; border-bottom: 1px solid #e3e7ed; vertical-align: top; }
        table.datos td.label { font-weight: bold; width: 180px; color: #22325a; }
        table.plan { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.plan th { text-align: left; background: #1a2537; color: #fff; padding: 8px; font-size: 12px; }
        table.plan td { padding: 8px; border-bottom: 1px solid #e3e7ed; }
        table.plan tfoot td { font-weight: bold; border-top: 2px solid #1a2537; border-bottom: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NETLEY</h1>
        <p>Plan de Pagos — Caso N° {{ $caso->id }}</p>
    </div>

    <div class="seccion">Datos del cliente</div>
    <table class="datos">
        <tr><td class="label">Nombre</td><td>{{ $caso->cliente->nombre_completo ?? '-' }}</td></tr>
        <tr><td class="label">Carnet de identidad</td><td>{{ $caso->cliente->ci ?? '-' }}</td></tr>
        <tr><td class="label">Materia legal</td><td>{{ $caso->materia_legal }}</td></tr>
        <tr><td class="label">Iguala</td><td>Bs. {{ number_format($caso->iguala, 2) }}</td></tr>
        <tr><td class="label">Saldo actual</td><td>Bs. {{ number_format($caso->saldo, 2) }}</td></tr>
    </table>

    <div class="seccion">Cuotas</div>
    <table class="plan">
        <thead>
            <tr>
                <th>Cuota #</th>
                <th>Fecha</th>
                <th>Monto</th>
                <th>Saldo tras esta cuota</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($planes as $plan)
                <tr>
                    <td>{{ $plan->numero === 0 ? 'Anticipo' : $plan->numero }}</td>
                    <td>{{ $plan->fecha->format('d/m/Y') }}</td>
                    <td>Bs. {{ number_format($plan->monto, 2) }}</td>
                    <td>Bs. {{ number_format($plan->nuevo_saldo, 2) }}</td>
                    <td>{{ $plan->estado->getLabel() }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sin plan de pagos registrado.</td></tr>
            @endforelse
        </tbody>
        @if ($planes->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="2">Total</td>
                    <td>Bs. {{ number_format($planes->sum('monto'), 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
