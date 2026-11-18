<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consultas Directas NETLEY</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #111; }
        .header { text-align: center; margin-bottom: 10px; }
        .header h1 { margin: 0; font-size: 18px; letter-spacing: 1px; }
        table.marco { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.marco td { border: 1px solid #000; padding: 5px 8px; vertical-align: middle; }
        .numero-caja { text-align: right; }
        .numero-caja .caja { display: inline-block; border: 1px solid #000; padding: 4px 18px; margin-left: 6px; font-weight: bold; }
        .titulo-seccion { background: #1a1a1a; color: #fff; font-weight: bold; font-size: 11px; padding: 4px 8px; text-transform: uppercase; }
        table.campos { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.campos td { border: 1px solid #000; padding: 5px 8px; vertical-align: top; }
        .label { font-size: 9px; color: #444; display: block; margin-bottom: 2px; }
        .valor { font-weight: bold; }
        .check { font-family: 'DejaVu Sans', sans-serif; margin-right: 3px; }
        .fila-check td { padding: 4px 8px; }
        .nota-caja { min-height: 40px; }
        .tipo-cita { font-weight: bold; text-transform: uppercase; vertical-align: middle !important; }
        .firma { margin-top: 26px; text-align: center; }
        .firma .linea { border-top: 1px solid #000; width: 260px; margin: 0 auto 4px; }
    </style>
</head>
<body>
    @php
        // Primera cita = la agendada; las siguientes, reagendadas (orden por fecha y hora).
        $reagendada = $citas->count() > 1;
        // Montos, servicios y nota: los de la cita vigente (la última no anulada).
        $vigente = $citas->where('anulada', false)->last() ?? $citas->last();
        $anio = ($consulta->created_at ?? now())->format('Y');
        $bs = fn ($monto) => $monto !== null ? number_format((float) $monto, fmod((float) $monto, 1.0) == 0.0 ? 0 : 2, '.', '') : '—';
        $tipoProceso = $consulta->delito?->delito ?? $consulta->delito_texto;
        $nota = collect([$vigente?->detalle, $consulta->nota_interna])->filter(fn ($texto) => filled($texto))->unique()->join("\n");
        $estado = $consulta->estado;
    @endphp

    <div class="header">
        <h1>CONSULTAS DIRECTAS{{ $reagendada ? ' REAGENDADAS' : '' }} {{ $anio }}</h1>
    </div>

    <table class="marco">
        <tr>
            <td style="width:24%;"><span class="label">Fecha de ingreso</span><span class="valor">{{ ($consulta->fecha_registro ?? $consulta->created_at)?->format('d-m-Y') ?? '—' }}</span></td>
            <td style="width:22%;"><span class="label">Ciudad</span><span class="valor">{{ $consulta->ciudad ?: '—' }}</span></td>
            <td style="width:30%;"><span class="label">Agendado por</span><span class="valor">{{ $consulta->atendidoPor?->nombre_completo ?: '—' }}</span></td>
            <td class="numero-caja" style="width:24%;">N°<span class="caja">{{ $consulta->id }}</span></td>
        </tr>
    </table>

    <div class="titulo-seccion">Forma de ingreso</div>
    <table class="campos">
        <tr>
            <td style="width:25%;"><span class="label">Forma de ingreso</span><span class="valor">{{ $consulta->forma_ingreso?->getLabel() ?? '—' }}</span></td>
            <td style="width:25%;"><span class="label">Origen</span><span class="valor">{{ $consulta->origen ?: '—' }}</span></td>
            <td style="width:50%;"><span class="label">Detalle</span><span class="valor">{{ $consulta->origen_detalle ?: '—' }}</span></td>
        </tr>
    </table>

    <div class="titulo-seccion">Datos del cliente</div>
    <table class="campos">
        <tr>
            <td style="width:20%;"><span class="label">N° de cliente</span><span class="valor">{{ $consulta->caso?->cliente?->nro_cliente ?? '—' }}</span></td>
            <td style="width:28%;"><span class="label">Nombre</span><span class="valor">{{ $consulta->nombres ?: '—' }}</span></td>
            <td style="width:30%;"><span class="label">Apellido</span><span class="valor">{{ trim($consulta->ap_paterno.' '.$consulta->ap_materno) ?: '—' }}</span></td>
            <td style="width:22%;"><span class="label">Teléfono</span><span class="valor">{{ \App\Support\Telefono::formatear($consulta->telefono) ?: '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Fecha de consulta</span><span class="valor">{{ $consulta->fecha_consulta?->format('d-m-Y') ?? '—' }}</span></td>
            <td><span class="label">Materia legal</span><span class="valor">{{ $consulta->materia_legal }}</span></td>
            <td colspan="2"><span class="label">Tipo de proceso</span><span class="valor">{{ $tipoProceso ?: '—' }}</span></td>
        </tr>
    </table>

    <div class="titulo-seccion">Iguala profesional</div>
    <table class="campos">
        <tr>
            <td style="width:25%;"><span class="label">Bs. Iguala Netley</span><span class="valor">{{ $bs($vigente?->bs_iguala) }}</span></td>
            <td style="width:25%;"><span class="label">Bs. Anticipo</span><span class="valor">{{ $bs($vigente?->bs_anticipo) }}</span></td>
            <td style="width:25%;"><span class="label">% Comisión</span><span class="valor">{{ $vigente?->porcentaje_comision !== null ? rtrim(rtrim(number_format((float) $vigente->porcentaje_comision, 2, '.', ''), '0'), '.').' %' : '—' }}</span></td>
            <td style="width:25%;"><span class="label">Bs. Comisión</span><span class="valor">{{ $bs($vigente?->bs_comision) }}</span></td>
        </tr>
    </table>

    <div class="titulo-seccion">Servicios requeridos</div>
    <table class="campos">
        <tr class="fila-check">
            <td style="width:25%;"><span class="check">{{ $vigente?->servicio_psicologia ? '☑' : '☐' }}</span>Psicología</td>
            <td style="width:25%;"><span class="check">{{ $vigente?->servicio_trabajo_social ? '☑' : '☐' }}</span>Social</td>
            <td style="width:50%;"><span class="check">{{ $vigente?->servicio_otro ? '☑' : '☐' }}</span>Otros{{ $vigente?->servicio_otro && $vigente->servicio_otro_detalle ? ': '.$vigente->servicio_otro_detalle : '' }}</td>
        </tr>
    </table>

    <div class="titulo-seccion">Nota</div>
    <table class="campos">
        <tr><td><div class="nota-caja">{!! nl2br(e($nota)) ?: '&nbsp;' !!}</div></td></tr>
    </table>

    {{-- Una fila por cita: la primera "Agendar cita", las demás "Cita reagendada". --}}
    <div class="titulo-seccion">Citas</div>
    <table class="campos">
        @forelse ($citas as $indice => $cita)
            <tr>
                <td class="tipo-cita" style="width:26%;">{{ $indice === 0 ? 'Agendar cita' : 'Cita reagendada' }}</td>
                <td style="width:38%;"><span class="label">Abogado(a)</span><span class="valor">{{ $cita->personal->pluck('nombre_completo')->join(', ') ?: '—' }}</span></td>
                <td style="width:18%;"><span class="label">Fecha</span><span class="valor">{{ $cita->fecha?->format('d-m-Y') ?? '—' }}</span></td>
                <td style="width:18%;"><span class="label">Hora</span><span class="valor">{{ substr((string) $cita->hora, 0, 5) ?: '—' }}</span>@if ($cita->anulada) <span style="color:#b91c1c;font-size:9px;">(anulada)</span>@endif</td>
            </tr>
        @empty
            <tr><td class="tipo-cita" style="width:26%;">Agendar cita</td><td colspan="3">Sin citas agendadas todavía.</td></tr>
        @endforelse
    </table>

    <div class="titulo-seccion">Cerrar caso</div>
    <table class="campos">
        <tr class="fila-check">
            <td style="width:19%;"><span class="check">{{ $consulta->caso_id !== null ? '☑' : '☐' }}</span>Cliente ejecutivo</td>
            <td style="width:16%;"><span class="check">{{ $reagendada ? '☑' : '☐' }}</span>Reagendar</td>
            <td style="width:17%;"><span class="check">{{ $estado === \App\Enums\EstadoConsulta::SoloConsulta ? '☑' : '☐' }}</span>Solo consulta</td>
            <td style="width:16%;"><span class="check">{{ in_array($estado, [\App\Enums\EstadoConsulta::NoContesta, \App\Enums\EstadoConsulta::NoResponde], true) ? '☑' : '☐' }}</span>No contesta</td>
            <td style="width:15%;"><span class="check">{{ $estado === \App\Enums\EstadoConsulta::NoAsistio ? '☑' : '☐' }}</span>No asistió</td>
            <td style="width:17%;"><span class="check">{{ $vigente?->anulada || $estado === \App\Enums\EstadoConsulta::CitaAnulada ? '☑' : '☐' }}</span>Cita anulada</td>
        </tr>
    </table>

    <div class="firma">
        <div class="linea"></div>
        <div style="font-weight:bold;">{{ $vigente?->personal->pluck('nombre_completo')->join(', ') ?: '—' }}</div>
        <div style="font-size:9px;color:#444;margin-top:2px;">ABOGADO RESPONSABLE</div>
    </div>
</body>
</html>
