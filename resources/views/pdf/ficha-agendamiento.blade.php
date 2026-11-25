<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consultas Directas NETLEY</title>
    {{-- Mismo diseño que la vista en pantalla (filament/admin/consultas/ficha-agendamiento),
         con tablas: DomPDF no maneja flex/grid ni degradados. --}}
    <style>
        @page { margin: 28px 34px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5px; color: #1f2937; margin: 0; }
        .azul { color: #233f94; }
        table { width: 100%; border-collapse: collapse; }

        .encabezado { border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 12px; }
        .encabezado td { vertical-align: middle; padding: 12px 14px; }
        .logo { width: 118px; }
        .cabecera { border-left: 1px solid #e5e7eb; padding-left: 16px !important; }
        .sobretitulo { font-size: 8px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #858686; }
        .titulo { font-family: 'Times New Roman', serif; font-size: 19px; letter-spacing: 1px; text-transform: uppercase; color: #233f94; margin: 3px 0; }
        .subtitulo { font-size: 11px; font-weight: bold; color: #4b5563; }
        .placa { width: 118px; background: #233f94; color: #fff; text-align: center; border-radius: 8px; padding: 8px 6px !important; }
        .placa-etiqueta { font-size: 7.5px; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; color: #c7d2fe; }
        .placa-numero { font-family: 'Times New Roman', serif; font-size: 30px; line-height: 1; margin: 3px 0 4px; padding-bottom: 4px; border-bottom: 1px solid #5b6fb8; }
        .placa-numero small { font-size: 11px; color: #c7d2fe; }
        .placa-pie { font-size: 8px; color: #dbe2fb; letter-spacing: 1px; }
        .franja td { height: 5px; padding: 0 !important; }

        .seccion { border: 1px solid #e5e7eb; border-radius: 8px; padding: 9px 12px 10px; margin-bottom: 9px; }
        .seccion h3 { margin: 0 0 7px; padding-bottom: 5px; border-bottom: 1px solid #e5e7eb; font-size: 8.5px; letter-spacing: 1.5px; text-transform: uppercase; color: #233f94; }
        .datos td { vertical-align: top; padding: 3px 8px 5px 0; }
        .et { display: block; font-size: 7.5px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; color: #6b7280; margin-bottom: 1px; }
        .va { font-weight: bold; font-size: 10.5px; }
        .chip { display: inline-block; padding: 2px 8px; margin-right: 4px; border: 1px solid #d1d5db; border-radius: 9px; color: #6b7280; font-size: 9px; }
        .chip.activo { border-color: #86c49b; background: #e8f6ed; color: #15803d; font-weight: bold; }
        .nota { margin-top: 3px; padding: 6px 8px; min-height: 26px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; }
        .vacio { color: #9ca3af; font-style: italic; font-weight: normal; }

        .cita td { padding: 6px 8px; vertical-align: middle; background: #f9fafb; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; }
        .cita td:first-child { border-left: 1px solid #e5e7eb; }
        .cita td:last-child { border-right: 1px solid #e5e7eb; text-align: right; }
        .separador td { height: 5px; padding: 0; }
        .tipo { font-size: 8.5px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; color: #233f94; }
        .anulada .va { text-decoration: line-through; color: #9ca3af; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 9px; font-size: 8.5px; font-weight: bold; }
        .badge.exito { background: #dcfce7; color: #15803d; }
        .badge.aviso { background: #fef3c7; color: #a16207; }
        .badge.peligro { background: #fee2e2; color: #b91c1c; }

        .check { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #233f94; }
        .cierre td { padding: 4px 6px 2px 0; font-size: 9.5px; }

        /* Espacio amplio para firmar a mano sobre la línea. */
        .firma { margin-top: 95px; text-align: center; }
        .firma .linea { border-top: 1px solid #6b7280; width: 280px; margin: 0 auto 5px; }
        .firma .cargo { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #6b7280; }
    </style>
</head>
<body>
    @php
        // Primera cita = la agendada; las siguientes, reagendadas (orden por fecha y hora).
        $reagendada = $citas->count() > 1;
        // Montos, servicios y nota: los de la cita vigente (la última no anulada).
        $vigente = $citas->where('anulada', false)->last() ?? $citas->last();
        $anio = ($consulta->created_at ?? now())->format('Y');
        $bs = fn ($monto) => $monto !== null ? 'Bs. '.number_format((float) $monto, 0, '.', '') : '—';
        $tipoProceso = $consulta->delito?->delito ?? $consulta->delito_texto;
        $nota = collect([$vigente?->detalle, $consulta->nota_interna])->filter(fn ($texto) => filled($texto))->unique()->join("\n");
        $estado = $consulta->estado;
        $logo = public_path('images/netley-logo-ficha.png');
        $marca = fn (bool $marcado) => $marcado ? '☑' : '☐';
    @endphp

    {{-- Encabezado: logo · título · placa con el N° --}}
    <table class="encabezado">
        <tr>
            <td style="width:130px;">
                @if (is_file($logo))
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logo)) }}" class="logo" alt="NETLEY">
                @endif
            </td>
            <td class="cabecera">
                <div class="sobretitulo">Ficha de agendamiento</div>
                <div class="titulo">Consultas directas{{ $reagendada ? ' reagendadas' : '' }} {{ $anio }}</div>
                <div class="subtitulo">{{ $consulta->nombre_completo }}</div>
            </td>
            <td style="width:130px;padding-right:12px;">
                <div class="placa">
                    <div class="placa-etiqueta">Consulta</div>
                    <div class="placa-numero"><small>N°</small> {{ str_pad((string) $consulta->id, 4, '0', STR_PAD_LEFT) }}</div>
                    <div class="placa-pie">{{ ($consulta->fecha_registro ?? $consulta->created_at)?->format('d/m/Y') }}</div>
                </div>
            </td>
        </tr>
    </table>
    <table class="franja" style="margin:-12px 0 12px;"><tr><td style="background:#233f94;"></td><td style="background:#858686;"></td></tr></table>

    <div class="seccion">
        <h3>Ingreso</h3>
        <table class="datos">
            <tr>
                <td style="width:33%;"><span class="et">Fecha de ingreso</span><span class="va">{{ ($consulta->fecha_registro ?? $consulta->created_at)?->format('d/m/Y') ?? '—' }}</span></td>
                <td style="width:33%;"><span class="et">Ciudad</span><span class="va">{{ $consulta->ciudad ?: '—' }}</span></td>
                <td style="width:34%;"><span class="et">Agendado por</span><span class="va">{{ $consulta->atendidoPor?->nombre_completo ?: '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="et">Forma de ingreso</span><span class="va">{{ $consulta->forma_ingreso?->getLabel() ?? '—' }}</span></td>
                <td><span class="et">Origen</span><span class="va">{{ $consulta->origen ?: '—' }}</span></td>
                <td><span class="et">Detalle</span><span class="va">{{ $consulta->origen_detalle ?: '—' }}</span></td>
            </tr>
        </table>
    </div>

    <div class="seccion">
        <h3>Datos del cliente</h3>
        <table class="datos">
            <tr>
                <td style="width:20%;"><span class="et">ID cliente</span><span class="va">{{ $consulta->caso?->cliente?->nro_cliente ?? '—' }}</span></td>
                <td style="width:27%;"><span class="et">Nombre</span><span class="va">{{ $consulta->nombres ?: '—' }}</span></td>
                <td style="width:28%;"><span class="et">Apellido</span><span class="va">{{ trim($consulta->ap_paterno.' '.$consulta->ap_materno) ?: '—' }}</span></td>
                <td style="width:25%;"><span class="et">Teléfono</span><span class="va">{{ \App\Support\Telefono::formatear($consulta->telefono) ?: '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="et">Fecha de consulta</span><span class="va">{{ $consulta->fecha_consulta?->format('d/m/Y') ?? '—' }}</span></td>
                <td><span class="et">Materia legal</span><span class="va">{{ $consulta->materia_legal }}</span></td>
                <td colspan="2"><span class="et">Tipo de proceso</span><span class="va">{{ $tipoProceso ?: '—' }}</span></td>
            </tr>
        </table>
    </div>

    <div class="seccion">
        <h3>Iguala profesional</h3>
        <table class="datos">
            <tr>
                <td style="width:25%;"><span class="et">Iguala Netley</span><span class="va">{{ $bs($vigente?->bs_iguala) }}</span></td>
                <td style="width:25%;"><span class="et">Anticipo</span><span class="va">{{ $bs($vigente?->bs_anticipo) }}</span></td>
                <td style="width:25%;"><span class="et">Comisión</span><span class="va">{{ $vigente?->porcentaje_comision !== null ? rtrim(rtrim(number_format((float) $vigente->porcentaje_comision, 2, '.', ''), '0'), '.').' %' : '—' }}</span></td>
                <td style="width:25%;"><span class="et">Comisión (Bs.)</span><span class="va">{{ $bs($vigente?->bs_comision) }}</span></td>
            </tr>
        </table>
        <div style="margin-top:6px;">
            <span class="et" style="display:inline;margin-right:6px;">Servicios requeridos</span>
            <span class="chip {{ $vigente?->servicio_psicologia ? 'activo' : '' }}">Psicología</span>
            <span class="chip {{ $vigente?->servicio_trabajo_social ? 'activo' : '' }}">Social</span>
            <span class="chip {{ $vigente?->servicio_otro ? 'activo' : '' }}">Otros{{ $vigente?->servicio_otro && $vigente->servicio_otro_detalle ? ': '.$vigente->servicio_otro_detalle : '' }}</span>
        </div>
        <div style="margin-top:7px;">
            <span class="et">Nota</span>
            <div class="nota">{!! filled($nota) ? nl2br(e($nota)) : '<span class="vacio">Sin nota</span>' !!}</div>
        </div>
    </div>

    <div class="seccion">
        <h3>Citas</h3>
        <table>
            @forelse ($citas as $indice => $cita)
                @if ($indice > 0)<tr class="separador"><td colspan="5"></td></tr>@endif
                <tr class="cita {{ $cita->anulada ? 'anulada' : '' }}">
                    <td style="width:20%;"><span class="tipo">{{ $indice === 0 ? 'Agendar cita' : 'Cita reagendada' }}</span></td>
                    <td style="width:38%;"><span class="et">Abogado(a)</span><span class="va">{{ $cita->personal->pluck('nombre_completo')->join(', ') ?: '—' }}</span></td>
                    <td style="width:14%;"><span class="et">Fecha</span><span class="va">{{ $cita->fecha?->format('d/m/Y') ?? '—' }}</span></td>
                    <td style="width:11%;"><span class="et">Hora</span><span class="va">{{ substr((string) $cita->hora, 0, 5) ?: '—' }}</span></td>
                    <td style="width:17%;">
                        @if ($cita->anulada)
                            <span class="badge peligro">Anulada</span>
                        @elseif ($cita->confirmada)
                            <span class="badge exito">Confirmada</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td class="vacio">Sin citas agendadas todavía.</td></tr>
            @endforelse
        </table>
    </div>

    <div class="seccion">
        <h3>Cerrar caso</h3>
        <table class="cierre">
            <tr>
                {{-- Siempre vacías: se marcan a mano en el papel. --}}
                <td><span class="check">☐</span> Cliente ejecutivo</td>
                <td><span class="check">☐</span> Reagendar</td>
                <td><span class="check">☐</span> Solo consulta</td>
                <td><span class="check">☐</span> No contesta</td>
                <td><span class="check">☐</span> No asistió</td>
                <td><span class="check">☐</span> Cita anulada</td>
            </tr>
        </table>
    </div>

    <div class="firma">
        <div class="linea"></div>
        <div style="font-weight:bold;">{{ $vigente?->personal->pluck('nombre_completo')->join(', ') ?: '—' }}</div>
        <div class="cargo">Abogado responsable</div>
    </div>
</body>
</html>
