@php
    $bs = fn ($valor) => 'Bs. '.number_format((float) $valor, 0);
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

    // Recuadro derecho (ver Pago::datosRecibo()).
    $caso = $pago->caso;
    $datos = $pago->datosRecibo();

    if ($datos['primero'] && $caso) {
        $hih = $caso->patrocinio_hih ? (float) $caso->monto_patrocinio : 0;
        $porcentaje = rtrim(rtrim(number_format((float) $caso->porcentaje_patrocinio, 2), '0'), '.');

        // Mismos nombres siempre; en costas y costos Caso.iguala ya incluye el patrocinio.
        $filasMonto = array_values(array_filter([
            ['Iguala profesional', $bs($caso->igualaProfesional()), false],
            $caso->patrocinio_hih ? ['Patrocinio HiH ('.$porcentaje.'%)', $bs($hih), false] : null,
            ['Iguala Netley', $bs($caso->esCostas() ? (float) $caso->iguala - $hih : $caso->iguala), true],
        ]));
        $filasMonto = array_values(array_filter([
            ...$filasMonto,
            ['Anticipo', $monto, false],
            ['Saldo', $bs($datos['saldo_total']), true],
        ]));
    } else {
        $filasMonto = [
            ['Saldo anterior', $bs($datos['saldo_anterior']), false],
            ['Anticipo', $monto, false],
            ['Saldo total', $bs($datos['saldo_total']), true],
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo N° {{ $numero }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1f2937; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; }

        /* Cada copia ocupa exactamente media hoja A4 (148,5 mm) y su marco
           tiene alto fijo: las dos copias quedan idénticas y la línea de
           corte cae justo al medio de la hoja. */
        .mitad { position: absolute; left: 10mm; right: 10mm; }
        .marco { border: 2px solid {{ $azul }}; border-radius: 8px; padding: 10px 14px; }
        /* Alto fijo de la parte superior (datos): las firmas siempre quedan a la misma altura. */
        .cuerpo { height: 90mm; vertical-align: top; }
        .copia { text-align: right; font-size: 8.5px; font-weight: bold; letter-spacing: 2px; color: #6b7280; margin-bottom: 3px; }
        .corte { position: absolute; left: 0; right: 0; top: 148.5mm; border-top: 1px dashed #9ca3af; text-align: center; font-size: 7.5px; color: #9ca3af; }
        .corte span { position: relative; top: -6px; background: #ffffff; padding: 0 8px; }
        .info { font-size: 8px; line-height: 1.4; color: #4b5563; }

        .logo-nombre { font-family: 'Times New Roman', serif; font-size: 36px; letter-spacing: 2px; color: {{ $azul }}; margin: 0; line-height: 1; }
        .logo-sub { font-family: 'Times New Roman', serif; font-size: 12px; color: #4b5563; margin-top: 2px; }

        .nit-label { font-size: 10px; font-weight: bold; color: {{ $azul }}; padding-right: 6px; }
        .nit-caja { border: 1px solid {{ $azul }}; border-radius: 4px; padding: 3px 8px; font-weight: bold; font-size: 12px; letter-spacing: 1px; }

        .fechas th { background: {{ $azul }}; color: #ffffff; font-size: 9px; letter-spacing: 1px; padding: 3px 0; font-weight: bold; }
        .fechas td { border: 1px solid {{ $azul }}; text-align: center; padding: 3px 4px; font-size: 10px; }

        .etiqueta-monto { text-align: right; font-size: 9.5px; font-weight: bold; color: {{ $azul }}; padding: 0 10px 0 0; white-space: nowrap; }
        .caja-monto { background: #f5f7fb; border: 1px solid #d1d5db; border-radius: 3px; padding: 2px 6px; min-height: 12px; font-size: 10px; }

        .titulo { font-size: 15px; font-weight: bold; letter-spacing: 1px; color: {{ $azul }}; margin: 0; }
        .numero-badge { display: inline-block; background: {{ $azul }}; color: #ffffff; font-size: 11px; font-weight: bold; padding: 3px 10px; border-radius: 4px; letter-spacing: 1px; }

        .campo-etiqueta { font-size: 10px; font-weight: bold; color: {{ $azul }}; white-space: nowrap; padding-right: 8px; }
        .campo-valor { border-bottom: 1px solid #9ca3af; padding: 2px 4px 1px; font-size: 10.5px; min-height: 14px; }

        .pago-titulo { font-size: 10px; font-weight: bold; color: {{ $azul }}; letter-spacing: 0.5px; padding-bottom: 4px; }
        .pago-campo { font-size: 10.5px; font-weight: bold; color: {{ $azul }}; white-space: nowrap; padding-right: 6px; }
        .pago-caja { border: 1px solid #9ca3af; border-radius: 4px; height: 16px; padding: 2px 6px; font-size: 10px; background: #ffffff; }

        .firma-titulo { font-size: 9px; font-weight: bold; letter-spacing: 1px; color: {{ $azul }}; text-align: center; border-bottom: 2px solid {{ $azul }}; padding-bottom: 4px; }
        .firma-etiqueta { font-size: 9px; font-weight: bold; color: {{ $azul }}; padding-right: 4px; width: 40px; }
        .firma-linea { border-bottom: 1px dotted #6b7280; font-size: 9.5px; padding: 2px 4px; height: 13px; }
    </style>
</head>
<body>
    {{-- Dos copias en la misma hoja: una se entrega al cliente y la otra
         se archiva (se cortan por la línea punteada). --}}
    <div class="corte"><span>cortar aquí</span></div>
    @foreach (['ORIGINAL — CLIENTE', 'COPIA — ARCHIVO'] as $copia)
        <div class="mitad" style="top: {{ $loop->first ? '11mm' : '159.5mm' }};">
        <div class="copia">{{ $copia }}</div>
        <div class="marco">
        <table><tr><td class="cuerpo">
        {{-- Recibo anulado (ver Contabilidad › Recibos anulados): marca bien visible. --}}
        @if ($pago->anulado)
            <div style="border: 2px solid #dc2626; color: #dc2626; text-align: center; font-weight: bold; font-size: 12px; letter-spacing: 3px; padding: 3px; margin-bottom: 4px;">
                RECIBO ANULADO
                <div style="font-size: 8.5px; letter-spacing: 0; font-weight: normal; margin-top: 1px;">
                    el {{ $pago->anulado_at?->format('d/m/Y H:i') }}{{ $pago->reemplazadoPor ? ' · reemplazado por el recibo N° '.str_pad($pago->reemplazadoPor->nro_recibo, 6, '0', STR_PAD_LEFT) : '' }}{{ $pago->motivo_anulacion ? ' · Motivo: '.$pago->motivo_anulacion : '' }}
                </div>
            </div>
        @endif
            <table>
                <tr>
                    <td style="width: 27%;">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/netley-logo-pdf.png'))) }}" style="width: 130px;" alt="NETLEY">
                    </td>
                    <td style="width: 36%; padding-top: 2px;">
                        <table style="width: auto;">
                            <tr>
                                <td class="nit-label" style="padding-top: 4px;">NIT:</td>
                                <td><div class="nit-caja">710912027</div></td>
                            </tr>
                        </table>
                        <div class="info" style="margin-top: 6px;">
                            Calle Murillo esq. Sagárnaga · Edif. Michel N° 189, Piso 6 Of. 601<br>
                            WhatsApp: +591 71536460 · info@netley.bo · www.netley.bo<br>
                            La Paz, Bolivia
                        </div>
                    </td>
                    <td style="width: 37%;">
                        <div style="text-align: right; margin-bottom: 5px;">
                            <span class="numero-badge">N° {{ $numero }}</span>
                        </div>
                        <table class="fechas" style="margin-bottom: 6px;">
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

                        {{-- Primer recibo del proceso: resumen completo (como el
                             paso 3). Los siguientes: solo saldo anterior, este
                             pago y el saldo que queda. --}}
                        <table>
                            @foreach ($filasMonto as [$etiqueta, $valor, $fuerte])
                                <tr>
                                    <td class="etiqueta-monto" style="width: 55%; padding-bottom: 4px;">{{ $etiqueta }}:</td>
                                    <td style="padding-bottom: 4px;"><div class="caja-monto"{!! $fuerte ? ' style="font-weight: bold;"' : '' !!}>{{ $valor }}</div></td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
            </table>

            <table style="margin-top: 6px;">
                <tr>
                    <td>
                        <div class="titulo">RECIBO</div>
                    </td>
                </tr>
            </table>

            <table style="margin-top: 6px;">
                <tr>
                    <td class="campo-etiqueta" style="width: 150px; padding-bottom: 5px;">ID cliente:</td>
                    <td class="campo-valor" style="padding-bottom: 5px;">{{ $pago->cliente->nro_cliente ?? $pago->cliente_id }}</td>
                </tr>
                <tr>
                    <td class="campo-etiqueta" style="padding-bottom: 5px;">Hemos recibido de:</td>
                    <td class="campo-valor" style="padding-bottom: 5px;">{{ $pago->cliente->nombre_completo }}</td>
                </tr>
                <tr>
                    <td class="campo-etiqueta" style="padding-bottom: 5px;">La suma de:</td>
                    <td class="campo-valor" style="padding-bottom: 5px;">{{ $pago->montoEnLetras() }}</td>
                </tr>
                <tr>
                    <td class="campo-etiqueta" style="padding-bottom: 5px;">Por concepto de:</td>
                    <td class="campo-valor" style="padding-bottom: 5px;">{{ $pago->concepto }} — Caso N° {{ $pago->caso_id }} — {{ $pago->caso?->materia_legal ?: "—" }} — {{ $pago->caso?->delito?->delito ?? ($pago->caso?->delito_texto ?: "—") }}</td>
                </tr>
            </table>

        </td></tr></table>

            {{-- Forma de pago y firmas: siempre al pie del marco. --}}
            <table>
                <tr>
                    <td style="width: 36%; padding-right: 14px;">
                        <div class="pago-titulo">FORMA DE PAGO</div>
                        <div class="pago-caja" style="height: auto; display: inline-block; min-width: 180px;">{{ $formaPagoTexto }}</div>
                    </td>
                    <td style="width: 64%;">
                        <table>
                            <tr>
                                <td style="width: 50%; padding-right: 12px;">
                                    <div class="firma-titulo">ENTREGUÉ CONFORME</div>
                                    <table style="margin-top: 12px;">
                                        <tr>
                                            <td class="firma-etiqueta">Nombre:</td>
                                            <td class="firma-linea">{{ $pago->cliente->nombre_completo }}</td>
                                        </tr>
                                        <tr><td style="height: 6px;"></td><td></td></tr>
                                        <tr>
                                            <td class="firma-etiqueta">C.I.:</td>
                                            <td class="firma-linea">{{ $pago->cliente->ci }}</td>
                                        </tr>
                                    </table>
                                </td>
                                <td style="width: 50%; padding-left: 12px;">
                                    <div class="firma-titulo">RECIBÍ CONFORME</div>
                                    <table style="margin-top: 12px;">
                                        <tr>
                                            <td class="firma-etiqueta">Nombre:</td>
                                            <td class="firma-linea">{{ $pago->registrado_por_nombre ?? '' }}</td>
                                        </tr>
                                        <tr><td style="height: 6px;"></td><td></td></tr>
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
        </div>
    @endforeach
</body>
</html>
