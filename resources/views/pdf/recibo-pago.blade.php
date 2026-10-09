@php
    $bs = fn ($valor) => 'Bs. '.number_format((float) $valor, 2);
    $monto = $bs($pago->monto);
    $tipo = $pago->tipo_pago;
    $forma = $pago->forma_pago;
    $numero = str_pad($pago->nro_recibo, 6, '0', STR_PAD_LEFT);
    $azul = '#1a2537';

    $formaPagoTexto = match ($forma) {
        'efectivo' => 'Efectivo',
        'qr' => 'QR',
        'cheque' => 'Cheque N° '.($pago->nro_cheque ?: '-').($pago->banco ? ' — Banco '.$pago->banco : ''),
        'banco' => 'Transferencia bancaria'.($pago->banco ? ' — Banco '.$pago->banco : ''),
        default => ucfirst((string) $forma),
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de ingreso N° {{ $numero }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 10mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; }

        .marco { border: 2px solid {{ $azul }}; border-radius: 8px; padding: 14px 18px; }

        .logo-nombre { font-family: 'Times New Roman', serif; font-size: 36px; letter-spacing: 2px; color: {{ $azul }}; margin: 0; line-height: 1; }
        .logo-sub { font-family: 'Times New Roman', serif; font-size: 12px; color: #4b5563; margin-top: 2px; }
        .info { font-size: 9.5px; line-height: 1.45; color: #4b5563; }

        .nit-label { font-size: 10px; font-weight: bold; color: {{ $azul }}; padding-right: 6px; }
        .nit-caja { border: 1px solid {{ $azul }}; border-radius: 4px; padding: 3px 8px; font-weight: bold; font-size: 12px; letter-spacing: 1px; }

        .fechas th { background: {{ $azul }}; color: #ffffff; font-size: 9px; letter-spacing: 1px; padding: 4px 0; font-weight: bold; }
        .fechas td { border: 1px solid {{ $azul }}; text-align: center; padding: 5px 4px; font-size: 11px; }

        .etiqueta-monto { text-align: right; font-size: 11px; font-weight: bold; color: {{ $azul }}; padding: 0 10px 0 0; white-space: nowrap; }
        .caja-monto { background: #f5f7fb; border: 1px solid #d1d5db; border-radius: 3px; padding: 4px 8px; min-height: 14px; font-size: 11px; }

        .titulo { font-size: 20px; font-weight: bold; letter-spacing: 1px; color: {{ $azul }}; margin: 0; }
        .numero-badge { display: inline-block; background: {{ $azul }}; color: #ffffff; font-size: 13px; font-weight: bold; padding: 4px 12px; border-radius: 4px; letter-spacing: 1px; }

        .campo-etiqueta { font-size: 11px; font-weight: bold; color: {{ $azul }}; white-space: nowrap; padding-right: 8px; }
        .campo-valor { border-bottom: 1px solid #9ca3af; padding: 3px 4px 2px; font-size: 12px; min-height: 16px; }

        .pago-titulo { font-size: 11px; font-weight: bold; color: {{ $azul }}; letter-spacing: 0.5px; padding-bottom: 6px; }
        .pago-campo { font-size: 10.5px; font-weight: bold; color: {{ $azul }}; white-space: nowrap; padding-right: 6px; }
        .pago-caja { border: 1px solid #9ca3af; border-radius: 4px; height: 20px; padding: 3px 8px; font-size: 11px; background: #ffffff; }

        .firma-titulo { font-size: 10.5px; font-weight: bold; letter-spacing: 1px; color: {{ $azul }}; text-align: center; border-bottom: 2px solid {{ $azul }}; padding-bottom: 4px; }
        .firma-etiqueta { font-size: 10px; font-weight: bold; color: {{ $azul }}; padding-right: 6px; width: 52px; }
        .firma-linea { border-bottom: 1px dotted #6b7280; font-size: 11px; padding: 2px 4px; height: 16px; }
    </style>
</head>
<body>
    <div class="marco">
        <table>
            <tr>
                <td style="width: 27%;">
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/netley-logo.png'))) }}" style="width: 200px;" alt="NETLEY">
                </td>
                <td style="width: 36%; padding-top: 2px;">
                    <table style="width: auto;">
                        <tr>
                            <td class="nit-label" style="padding-top: 4px;">NIT:</td>
                            <td><div class="nit-caja">710912027</div></td>
                        </tr>
                    </table>
                    <div class="info" style="margin-top: 8px;">
                        Calle Murillo esq. Sagárnaga · Edif. Michel N° 189, Piso 6 Of. 601<br>
                        WhatsApp: 71536460 · info@netley.bo · www.netley.bo<br>
                        La Paz, Bolivia
                    </div>
                </td>
                <td style="width: 37%;">
                    <div style="text-align: right; margin-bottom: 8px;">
                        <span class="numero-badge">N° {{ $numero }}</span>
                    </div>
                    <table class="fechas" style="margin-bottom: 10px;">
                        <tr>
                            <th style="width: 40%;">CIUDAD</th>
                            <th style="width: 20%;">DÍA</th>
                            <th style="width: 20%;">MES</th>
                            <th style="width: 20%;">AÑO</th>
                        </tr>
                        <tr>
                            <td>{{ ucwords(strtolower($pago->sucursal)) }}</td>
                            <td>{{ $pago->fecha_pago->format('d') }}</td>
                            <td>{{ $pago->fecha_pago->format('m') }}</td>
                            <td>{{ $pago->fecha_pago->format('Y') }}</td>
                        </tr>
                    </table>

                    <table>
                        <tr><td class="etiqueta-monto" style="width: 55%; padding-bottom: 5px;">Anticipo Cliente:</td><td style="padding-bottom: 5px;"><div class="caja-monto">{{ $tipo === 'anticipo' ? $monto : '' }}</div></td></tr>
                        <tr><td class="etiqueta-monto" style="padding-bottom: 5px;">Saldo a Pagar Cliente:</td><td style="padding-bottom: 5px;"><div class="caja-monto">{{ $pago->caso ? $bs($pago->caso->saldo) : '' }}</div></td></tr>
                        <tr><td class="etiqueta-monto">ID Cliente:</td><td><div class="caja-monto">{{ $pago->cliente_id }}</div></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table style="margin-top: 14px;">
            <tr>
                <td>
                    <div class="titulo">COMPROBANTE DE INGRESO</div>
                </td>
            </tr>
        </table>

        <table style="margin-top: 14px;">
            <tr>
                <td class="campo-etiqueta" style="width: 150px; padding-bottom: 8px;">Hemos recibido de:</td>
                <td class="campo-valor" style="padding-bottom: 8px;">{{ $pago->cliente->nombre_completo }}</td>
            </tr>
            <tr>
                <td class="campo-etiqueta" style="padding-bottom: 8px;">La suma de:</td>
                <td class="campo-valor" style="padding-bottom: 8px;">{{ $pago->montoEnLetras() }}</td>
            </tr>
            <tr>
                <td class="campo-etiqueta" style="padding-bottom: 8px;">Por concepto de:</td>
                <td class="campo-valor" style="padding-bottom: 8px;">Cuota N° {{ $pago->nro_cuota }} — Caso N° {{ $pago->caso_id }} ({{ $pago->caso->materia_legal }})</td>
            </tr>
        </table>

        <table style="margin-top: 16px;">
            <tr>
                <td style="width: 58%; padding-right: 16px;">
                    <div class="pago-titulo">FORMA DE PAGO</div>
                    <div class="pago-caja" style="height: auto; display: inline-block; min-width: 260px;">{{ $formaPagoTexto }}</div>
                </td>
                <td style="width: 42%;">
                    <table>
                        <tr>
                            <td style="width: 50%; padding-right: 12px;">
                                <div class="firma-titulo">ENTREGUÉ CONFORME</div>
                                <table style="margin-top: 26px;">
                                    <tr>
                                        <td class="firma-etiqueta">Nombre:</td>
                                        <td class="firma-linea">{{ $pago->cliente->nombre_completo }}</td>
                                    </tr>
                                    <tr><td style="height: 12px;"></td><td></td></tr>
                                    <tr>
                                        <td class="firma-etiqueta">C.I.:</td>
                                        <td class="firma-linea">{{ $pago->cliente->ci }}</td>
                                    </tr>
                                </table>
                            </td>
                            <td style="width: 50%; padding-left: 12px;">
                                <div class="firma-titulo">RECIBÍ CONFORME</div>
                                <table style="margin-top: 26px;">
                                    <tr>
                                        <td class="firma-etiqueta">Nombre:</td>
                                        <td class="firma-linea">{{ $pago->registrado_por_nombre ?? '' }}</td>
                                    </tr>
                                    <tr><td style="height: 12px;"></td><td></td></tr>
                                    <tr>
                                        <td class="firma-etiqueta">C.I.:</td>
                                        <td class="firma-linea">{{ $pago->registrado_por_ci ?? '' }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
