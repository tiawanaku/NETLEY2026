@php
    $azul = '#1a2537';
    // Montos sin separador de miles (Bs. 15000), con centavos solo si los hay.
    $bs = function ($valor): string {
        $valor = (float) $valor;

        return 'Bs. '.(fmod($valor, 1.0) == 0.0 ? number_format($valor, 0, '.', '') : number_format($valor, 2, '.', ''));
    };
    $fecha = fn ($f) => $f ? $f->format('d/m/Y') : '—';
    $cliente = $caso->cliente;

    $igualaNetley = (float) $caso->iguala;
    $hih = (float) ($caso->monto_patrocinio ?? 0);
    $igualaProfesional = $igualaNetley + ($caso->patrocinio_hih ? $hih : 0);

    $servicios = collect([
        ['activo' => $caso->servicio_psicologia, 'nombre' => 'Psicología', 'monto' => $caso->monto_servicio_psicologia],
        ['activo' => $caso->servicio_trabajo_social, 'nombre' => 'Trabajo social', 'monto' => $caso->monto_servicio_trabajo_social],
        ['activo' => $caso->servicio_otro, 'nombre' => 'Otro'.($caso->servicio_otro_detalle ? ': '.$caso->servicio_otro_detalle : ''), 'monto' => $caso->monto_servicio_otro],
    ])->where('activo', true)->values();

    $cuotas = $caso->planesPago->sortBy('numero')->values();
    $pagos = $caso->pagos->sortBy(fn ($p) => [$p->fecha_pago?->timestamp, $p->id])->values();
    // Cuotas del plan que se cubrieron con el anticipo (mismo criterio que
    // la pestaña Plan de pagos: cuota 0, o la del pago de tipo anticipo).
    $cuotasAnticipo = $caso->pagos->where('tipo_pago', 'anticipo')->pluck('nro_cuota')->map(fn ($n) => (int) $n)->all();
    $denuncias = $caso->delitosDenunciados->pluck('delito')->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe rápido del caso N° {{ $caso->id }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 14mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5px; color: #1f2937; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }

        .encabezado td { vertical-align: middle; }
        .titulo { font-size: 17px; font-weight: bold; color: {{ $azul }}; margin: 0; text-align: right; }
        .subtitulo { font-size: 10px; color: #6b7280; text-align: right; margin-top: 3px; }
        .badge { display: inline-block; background: {{ $azul }}; color: #fff; font-weight: bold; font-size: 11px; padding: 3px 10px; border-radius: 4px; margin-top: 4px; }

        .seccion { margin: 14px 0 6px; font-size: 11.5px; font-weight: bold; color: {{ $azul }}; border-bottom: 2px solid {{ $azul }}; padding-bottom: 3px; text-transform: uppercase; letter-spacing: .5px; }

        table.datos td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
        table.datos td.et { font-weight: bold; color: #22325a; width: 17%; white-space: nowrap; }
        table.datos td.va { width: 33%; }

        table.lista th { background: {{ $azul }}; color: #fff; font-size: 9.5px; padding: 4px 6px; text-align: left; }
        table.lista td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
        table.lista tr.par td { background: #f5f7fb; }
        .der, table.lista th.der { text-align: right; }

        .texto { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; white-space: pre-wrap; }
        .resumen td { padding: 5px 8px; border: 1px solid #d1d5db; }
        .resumen td.et { background: #f5f7fb; font-weight: bold; color: #22325a; width: 25%; }
        .fuerte { font-weight: bold; }
        .pagado { color: #166534; font-weight: bold; }
        .pendiente { color: #92400e; font-weight: bold; }
        .vacio { color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td style="width: 45%;">
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/netley-logo-pdf.png'))) }}" style="width: 150px;" alt="NETLEY">
            </td>
            <td style="width: 55%;">
                <p class="titulo">Informe rápido del caso</p>
                <div style="text-align: right;"><span class="badge">Caso N° {{ $caso->id }}</span></div>
                <div class="subtitulo">Emitido el {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="seccion">Datos del cliente</div>
    <table class="datos">
        <tr>
            <td class="et">Nombre</td><td class="va">{{ $cliente?->nombre_completo ?: '—' }}</td>
            <td class="et">C.I.</td><td class="va">{{ $cliente?->ci ?: '—' }}{{ $cliente?->extension ? " ({$cliente->extension})" : '' }}</td>
        </tr>
        <tr>
            <td class="et">Teléfono</td><td class="va">{{ \App\Support\Telefono::formatear($cliente?->telefono) ?: '—' }}</td>
            <td class="et">WhatsApp</td><td class="va">{{ \App\Support\Telefono::formatear($cliente?->whatsapp) ?: '—' }}</td>
        </tr>
        <tr>
            <td class="et">Correo</td><td class="va">{{ $cliente?->correo ?: '—' }}</td>
            <td class="et">Dirección</td><td class="va">{{ $cliente?->direccion ?: '—' }}</td>
        </tr>
    </table>

    <div class="seccion">Datos del caso</div>
    <table class="datos">
        <tr>
            <td class="et">Materia legal</td><td class="va">{{ $caso->materia_legal ?: '—' }}</td>
            <td class="et">Delito</td><td class="va">{{ $caso->delito?->delito ?? ($caso->delito_texto ?: '—') }}</td>
        </tr>
        <tr>
            <td class="et">Apersonamiento</td><td class="va">{{ $caso->apersonamiento ?: '—' }}</td>
            <td class="et">Estado</td><td class="va">{{ $caso->estado?->getLabel() ?? '—' }}</td>
        </tr>
        <tr>
            <td class="et">Abogado(s)</td><td class="va">{{ $caso->personal->pluck('nombre_completo')->implode(', ') ?: '—' }}</td>
            <td class="et">Ciudad</td><td class="va">{{ $caso->ciudad ?: '—' }}</td>
        </tr>
        <tr>
            <td class="et">Denunciado</td><td class="va">{{ $caso->denunciado ?: '—' }}</td>
            <td class="et">Denuncias</td><td class="va">{{ $denuncias ?: '—' }}</td>
        </tr>
        <tr>
            <td class="et">Fecha de inicio</td><td class="va">{{ $fecha($caso->fecha_inicio) }}</td>
            <td class="et">Vencimiento</td><td class="va">{{ $fecha($caso->fecha_fin) }}{{ $caso->duracion_meses ? " ({$caso->duracion_meses} meses)" : '' }}</td>
        </tr>
    </table>

    @if ($caso->descripcion)
        <div style="margin-top: 8px; font-weight: bold; color: #22325a;">Descripción</div>
        <div class="texto">{{ $caso->descripcion }}</div>
    @endif

    <div class="seccion">Servicios requeridos</div>
    @if ($servicios->isEmpty())
        <p class="vacio" style="margin: 2px 0;">No se solicitaron servicios extra.</p>
    @else
        <table class="lista">
            <tr><th>Servicio</th><th class="der" style="width: 25%;">Monto</th></tr>
            @foreach ($servicios as $i => $servicio)
                <tr class="{{ $i % 2 ? 'par' : '' }}">
                    <td>{{ $servicio['nombre'] }}</td>
                    <td class="der">{{ $servicio['monto'] !== null ? $bs($servicio['monto']) : '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="seccion">Finanzas</div>
    <table class="resumen">
        <tr>
            <td class="et">Iguala profesional</td><td>{{ $bs($igualaProfesional) }}</td>
            <td class="et">Pagado</td><td class="pagado">{{ $bs($caso->pagado) }}</td>
        </tr>
        <tr>
            <td class="et">HiH</td>
            <td>{{ $caso->patrocinio_hih ? $bs($hih).($caso->porcentaje_patrocinio ? ' ('.rtrim(rtrim(number_format((float) $caso->porcentaje_patrocinio, 2, '.', ''), '0'), '.').'%)' : '') : 'No' }}</td>
            <td class="et">Saldo</td><td class="pendiente">{{ $bs($caso->saldo) }}</td>
        </tr>
        <tr>
            <td class="et">Iguala Netley</td><td class="fuerte">{{ $bs($igualaNetley) }}</td>
            <td class="et">Comisión</td>
            <td>{{ collect([
                (float) $caso->comision_porcentaje > 0 ? rtrim(rtrim(number_format((float) $caso->comision_porcentaje, 2, '.', ''), '0'), '.').'%' : null,
                (float) $caso->comision_monto > 0 ? $bs($caso->comision_monto) : null,
            ])->filter()->implode(' · ') ?: '—' }}</td>
        </tr>
    </table>

    <div class="seccion">Plan de pagos</div>
    @if ($cuotas->isEmpty())
        <p class="vacio" style="margin: 2px 0;">Sin plan de pagos.</p>
    @else
        <table class="lista">
            <tr><th style="width: 18%;">Cuota</th><th>Fecha</th><th class="der">Monto</th><th class="der">Saldo después</th><th style="width: 16%;">Estado</th></tr>
            @foreach ($cuotas as $i => $cuota)
                <tr class="{{ $i % 2 ? 'par' : '' }}">
                    <td>{{ match (true) { (int) $cuota->numero === 0 => 'Anticipo', in_array((int) $cuota->numero, $cuotasAnticipo, true) => 'Cuota '.$cuota->numero.' · Anticipo', default => 'Cuota '.$cuota->numero } }}</td>
                    <td>{{ $fecha($cuota->fecha) }}</td>
                    <td class="der">{{ $bs($cuota->monto) }}</td>
                    <td class="der">{{ $cuota->nuevo_saldo !== null ? $bs($cuota->nuevo_saldo) : '—' }}</td>
                    <td class="{{ $cuota->estado?->value === 'pagado' ? 'pagado' : 'pendiente' }}">{{ $cuota->estado?->getLabel() ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="seccion">Pagos realizados</div>
    @if ($pagos->isEmpty())
        <p class="vacio" style="margin: 2px 0;">Todavía no hay pagos registrados.</p>
    @else
        <table class="lista">
            <tr><th style="width: 14%;">Fecha</th><th style="width: 12%;">Recibo</th><th>Concepto</th><th>Forma de pago</th><th class="der">Monto</th></tr>
            @foreach ($pagos as $i => $pago)
                <tr class="{{ $i % 2 ? 'par' : '' }}">
                    <td>{{ $fecha($pago->fecha_pago) }}</td>
                    <td>{{ $pago->nro_recibo ? 'N° '.$pago->nro_recibo : '—' }}</td>
                    <td>{{ $pago->concepto }}</td>
                    <td>{{ $pago->forma_pago ? ucfirst($pago->forma_pago) : '—' }}</td>
                    <td class="der">{{ $bs($pago->monto) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="4" class="der fuerte">Total pagado</td>
                <td class="der pagado">{{ $bs($pagos->sum('monto')) }}</td>
            </tr>
        </table>
    @endif
</body>
</html>
