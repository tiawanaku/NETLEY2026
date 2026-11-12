{{-- Resumen del paso 3 ("Pago y plan de cuotas") arriba del Plan de pagos (ver PlanesPagoRelationManager::content()). --}}
@php
    $tarjeta = 'border:1px solid rgba(128,128,128,.25);border-radius:10px;padding:10px 14px;';
    $etiqueta = 'font-size:12px;opacity:.65;margin-bottom:2px;';
    $valor = 'font-size:15px;font-weight:600;';
@endphp
<div style="border:1px solid rgba(128,128,128,.25);border-radius:12px;padding:16px;margin-bottom:16px;">
    <div style="font-weight:700;font-size:15px;margin-bottom:12px;">Resumen</div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;">
        @foreach ($filas as $fila)
            <div style="{{ $tarjeta }}{{ $fila['fondo'] ?? '' }}">
                <div style="{{ $etiqueta }}">{{ $fila['etiqueta'] }}</div>
                <div style="{{ $valor }}{{ $fila['color'] ?? '' }}">{{ $fila['valor'] }}</div>
            </div>
        @endforeach
    </div>

    <div style="font-weight:700;font-size:14px;margin:16px 0 8px;">Pago de servicios extra</div>
    @if (empty($servicios))
        <div style="font-size:13px;opacity:.65;">No se solicitaron servicios extra.</div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;">
            @foreach ($servicios as $servicio)
                <div style="{{ $tarjeta }}">
                    <div style="{{ $etiqueta }}">{{ $servicio['nombre'] }}</div>
                    <div style="{{ $valor }}">{{ $servicio['monto'] }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Alternativa: el mismo resumen en forma de tabla (a pedido, para comparar con las tarjetas de arriba). --}}
@php
    $celda = 'padding:7px 12px;border-bottom:1px solid rgba(128,128,128,.2);';
    $mitad = (int) ceil(count($filas) / 2);
    $columnas = [array_slice($filas, 0, $mitad), array_slice($filas, $mitad)];
@endphp
<div style="border:1px solid rgba(128,128,128,.25);border-radius:12px;padding:16px;margin-bottom:16px;">
    <div style="font-weight:700;font-size:15px;margin-bottom:12px;">Resumen (tabla)</div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:0 24px;align-items:start;">
        @foreach ($columnas as $columna)
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tbody>
                    @foreach ($columna as $fila)
                        <tr>
                            <td style="{{ $celda }}opacity:.7;">{{ $fila['etiqueta'] }}</td>
                            <td style="{{ $celda }}text-align:right;font-weight:600;{{ $fila['color'] ?? '' }}">{{ $fila['valor'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>

    <div style="font-weight:700;font-size:14px;margin:16px 0 6px;">Pago de servicios extra</div>
    @if (empty($servicios))
        <div style="font-size:13px;opacity:.65;">No se solicitaron servicios extra.</div>
    @else
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.35);">
                    <th style="padding:7px 12px;font-weight:600;">Servicio</th>
                    <th style="padding:7px 12px;font-weight:600;text-align:right;">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($servicios as $servicio)
                    <tr>
                        <td style="{{ $celda }}">{{ $servicio['nombre'] }}</td>
                        <td style="{{ $celda }}text-align:right;font-weight:600;">{{ $servicio['monto'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
