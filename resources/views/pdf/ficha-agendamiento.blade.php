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
        .nota-caja { min-height: 26px; }
        .firma { margin-top: 26px; text-align: center; }
        .firma .linea { border-top: 1px solid #000; width: 260px; margin: 0 auto 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONSULTAS DIRECTAS NETLEY 2026</h1>
    </div>

    <table class="marco">
        <tr>
            <td style="width:33%;"><span class="label">Fecha de registro</span><span class="valor">{{ $consulta->created_at?->format('d/m/Y') ?? '—' }}</span></td>
            <td style="width:33%;"><span class="label">Atendido por</span><span class="valor">{{ $consulta->atendidoPor?->nombre_completo ?: '—' }}</span></td>
            <td class="numero-caja" style="width:34%;">N°<span class="caja">{{ $consulta->id }}</span></td>
        </tr>
    </table>

    @php
        $origenTexto = (string) ($consulta->origen ?? '');
        $origenNormalizado = mb_strtolower(trim($origenTexto));
        $esTaller = \App\Support\Origenes::esTalleres($consulta->origen);
        $esPsicologia = str_contains($origenNormalizado, 'psicolog');
        $esRedesSociales = ! $esTaller && ! $esPsicologia && collect(['facebook', 'instagram', 'tiktok', 'whatsapp', 'redes', 'x', 'youtube', 'linkedin'])
            ->contains(fn ($red) => str_contains($origenNormalizado, $red));
        $esOtros = $origenTexto !== '' && ! $esTaller && ! $esPsicologia && ! $esRedesSociales;
    @endphp
    <div class="titulo-seccion">Forma de ingreso</div>
    <table class="campos">
        <tr>
            <td style="width:25%;"><span class="check">{{ $esRedesSociales ? '☑' : '☐' }}</span>Redes Sociales</td>
            <td style="width:25%;"><span class="check">{{ $esPsicologia ? '☑' : '☐' }}</span>Psicología</td>
            <td style="width:25%;"><span class="check">{{ $esTaller ? '☑' : '☐' }}</span>Taller</td>
            <td style="width:25%;"><span class="check">{{ $esOtros ? '☑' : '☐' }}</span>Otros{{ $esOtros ? ': '.$origenTexto : '' }}</td>
        </tr>
        <tr>
            <td><span class="label">Ciudad</span><span class="valor">{{ $consulta->ciudad ?: '—' }}</span></td>
            <td colspan="3"><span class="label">Nombre colegio / otros</span><span class="valor">{{ $consulta->origen_detalle ?: '—' }}</span></td>
        </tr>
    </table>

    <div class="titulo-seccion">Datos del cliente</div>
    <table class="campos">
        <tr>
            <td style="width:25%;"><span class="label">ID Cliente</span><span class="valor">{{ $consulta->caso?->cliente?->nro_cliente ?? '—' }}</span></td>
            <td style="width:40%;"><span class="label">Nombre</span><span class="valor">{{ $consulta->nombres }}</span></td>
            <td style="width:35%;"><span class="label">Teléfono</span><span class="valor">{{ \App\Support\Telefono::formatear($consulta->telefono) ?: '—' }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="label">Apellido</span><span class="valor">{{ trim($consulta->ap_paterno.' '.$consulta->ap_materno) ?: '—' }}</span></td>
            <td><span class="label">Nota</span><span class="valor">{{ $consulta->nota_interna ?: '—' }}</span></td>
        </tr>
    </table>

    <div class="titulo-seccion">Tipo de proceso</div>
    <table class="campos">
        <tr><td><span class="valor">{{ $consulta->materia_legal }}</span></td></tr>
    </table>

    @forelse ($citas as $indice => $cita)
        <div class="titulo-seccion">
            {{ $indice === 0 ? 'Agendar' : 'Reprogramación '.$indice }}
            — Abogado(a): {{ $cita->personal->pluck('nombres')->join(', ') ?: '—' }}
        </div>
        <table class="campos">
            <tr>
                <td style="width:20%;"><span class="label">Fecha</span><span class="valor">{{ $cita->fecha?->format('d/m/Y') ?? '—' }}</span></td>
                <td style="width:20%;"><span class="label">Hora</span><span class="valor">{{ substr((string) $cita->hora, 0, 5) ?: '—' }}</span></td>
                <td style="width:20%;"><span class="label">Bs. Iguala</span><span class="valor">{{ $cita->bs_iguala !== null ? number_format((float) $cita->bs_iguala, 2) : '—' }}</span></td>
                <td style="width:20%;"><span class="label">Bs. Anticipo</span><span class="valor">{{ $cita->bs_anticipo !== null ? number_format((float) $cita->bs_anticipo, 2) : '—' }}</span></td>
                <td style="width:20%;"><span class="label">% Comisión</span><span class="valor">{{ $cita->porcentaje_comision !== null ? number_format((float) $cita->porcentaje_comision, 2).'%' : '—' }}</span></td>
            </tr>
            <tr class="fila-check">
                <td><span class="label">Bs. Comisión</span><span class="valor">{{ $cita->bs_comision !== null ? number_format((float) $cita->bs_comision, 2) : '—' }}</span></td>
                <td colspan="2"><span class="check">{{ $cita->confirmada ? '☑' : '☐' }}</span>Cita confirmada</td>
                <td colspan="2"><span class="check">{{ $cita->anulada ? '☑' : '☐' }}</span>Cita anulada</td>
            </tr>
            <tr>
                <td colspan="5"><span class="label">Nota</span><div class="nota-caja">{{ $cita->detalle ?: '—' }}</div></td>
            </tr>
        </table>
    @empty
        <div class="titulo-seccion">Agendar</div>
        <table class="campos">
            <tr><td>Sin citas agendadas todavía.</td></tr>
        </table>
    @endforelse

    @php
        $vigente = $citas->firstWhere('anulada', false) ?? $citas->first();
    @endphp
    <div class="titulo-seccion">Cerrar caso</div>
    <table class="campos">
        <tr class="fila-check">
            <td style="width:20%;"><span class="check">{{ $consulta->caso_id !== null ? '☑' : '☐' }}</span>Cliente ejecutivo</td>
            <td style="width:22%;"><span class="check">{{ $consulta->estado === \App\Enums\EstadoConsulta::RemitirPsicologia ? '☑' : '☐' }}</span>Tb. necesita Psicología</td>
            <td style="width:16%;"><span class="check">{{ $consulta->estado === \App\Enums\EstadoConsulta::SoloConsulta ? '☑' : '☐' }}</span>Solo consulta</td>
            <td style="width:16%;"><span class="check">{{ $consulta->estado === \App\Enums\EstadoConsulta::NoAsistio ? '☑' : '☐' }}</span>No asistió</td>
            <td style="width:13%;"><span class="check">{{ $consulta->estado === \App\Enums\EstadoConsulta::NoResponde ? '☑' : '☐' }}</span>No responde</td>
            <td style="width:13%;"><span class="check">{{ $vigente?->anulada ? '☑' : '☐' }}</span>Cita anulada</td>
        </tr>
        <tr>
            <td colspan="6"><span class="label">Observación</span><div class="nota-caja">{{ $consulta->nota_interna ?: '—' }}</div></td>
        </tr>
    </table>

    <div class="firma">
        <div class="linea"></div>
        <div style="font-weight:bold;">{{ $vigente?->personal->pluck('nombre_completo')->join(', ') ?: '—' }}</div>
        <div style="font-size:9px;color:#444;margin-top:2px;">ABOGADO RESPONSABLE</div>
    </div>
</body>
</html>
