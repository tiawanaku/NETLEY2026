@php
    $origenTexto = (string) ($consulta->origen ?? '');
    $origenNormalizado = mb_strtolower(trim($origenTexto));
    $esTaller = \App\Support\Origenes::esTalleres($consulta->origen);
    $esPsicologia = str_contains($origenNormalizado, 'psicolog');
    $esRedesSociales = ! $esTaller && ! $esPsicologia && collect(['facebook', 'instagram', 'tiktok', 'whatsapp', 'redes', 'x', 'youtube', 'linkedin'])
        ->contains(fn ($red) => str_contains($origenNormalizado, $red));
    $esOtros = $origenTexto !== '' && ! $esTaller && ! $esPsicologia && ! $esRedesSociales;
    $vigente = $citas->firstWhere('anulada', false) ?? $citas->first();
@endphp

<div style="overflow-x:auto;">
    <div style="font-family: Arial, Helvetica, sans-serif; color:#111; background:#fff; border:1px solid #000; padding:16px; min-width:720px; border-radius:4px;">

        <div style="text-align:center;margin-bottom:12px;">
            <h2 style="margin:0;font-size:19px;letter-spacing:1px;font-weight:800;">CONSULTAS DIRECTAS NETLEY 2026</h2>
        </div>

        <table style="width:100%;border-collapse:collapse;margin-bottom:10px;font-size:12px;">
            <tr>
                <td style="border:1px solid #000;padding:6px 10px;width:33%;">
                    <span style="display:block;font-size:9px;color:#444;">Fecha de registro</span>
                    <strong>{{ $consulta->created_at?->format('d/m/Y') ?? '—' }}</strong>
                </td>
                <td style="border:1px solid #000;padding:6px 10px;width:34%;">
                    <span style="display:block;font-size:9px;color:#444;">Atendido por</span>
                    <strong>{{ $vigente?->personal?->pluck('nombre_completo')->join(', ') ?: '—' }}</strong>
                </td>
                <td style="border:1px solid #000;padding:6px 10px;width:33%;text-align:right;">
                    N°
                    <span style="display:inline-block;border:1px solid #000;padding:3px 16px;margin-left:6px;font-weight:700;">{{ $consulta->id }}</span>
                </td>
            </tr>
        </table>

        <div style="background:#1a1a1a;color:#fff;font-weight:700;font-size:11px;padding:4px 10px;text-transform:uppercase;">Forma de ingreso</div>
        <table style="width:100%;border-collapse:collapse;margin-bottom:10px;font-size:12px;">
            <tr>
                <td style="border:1px solid #000;padding:5px 10px;width:25%;"><span style="margin-right:3px;">{{ $esRedesSociales ? '☑' : '☐' }}</span>Redes Sociales</td>
                <td style="border:1px solid #000;padding:5px 10px;width:25%;"><span style="margin-right:3px;">{{ $esPsicologia ? '☑' : '☐' }}</span>Psicología</td>
                <td style="border:1px solid #000;padding:5px 10px;width:25%;"><span style="margin-right:3px;">{{ $esTaller ? '☑' : '☐' }}</span>Taller</td>
                <td style="border:1px solid #000;padding:5px 10px;width:25%;"><span style="margin-right:3px;">{{ $esOtros ? '☑' : '☐' }}</span>Otros{{ $esOtros ? ': '.\App\Support\Origenes::texto($consulta->origen, $consulta->origen_detalle) : '' }}</td>
            </tr>
            <tr>
                <td style="border:1px solid #000;padding:5px 10px;">
                    <span style="display:block;font-size:9px;color:#444;">Ciudad</span>
                    <strong>{{ $consulta->ciudad ?: '—' }}</strong>
                </td>
                <td style="border:1px solid #000;padding:5px 10px;" colspan="3">
                    <span style="display:block;font-size:9px;color:#444;">Nombre colegio / otros</span>
                    <strong>{{ ($esTaller ? $consulta->origen_detalle : null) ?: '—' }}</strong>
                </td>
            </tr>
        </table>

        <div style="background:#1a1a1a;color:#fff;font-weight:700;font-size:11px;padding:4px 10px;text-transform:uppercase;">Datos del cliente</div>
        <table style="width:100%;border-collapse:collapse;margin-bottom:10px;font-size:12px;">
            <tr>
                <td style="border:1px solid #000;padding:5px 10px;width:20%;">
                    <span style="display:block;font-size:9px;color:#444;">ID Cliente</span>
                    <strong>{{ $consulta->caso?->cliente?->nro_cliente ?? '—' }}</strong>
                </td>
                <td style="border:1px solid #000;padding:5px 10px;width:45%;">
                    <span style="display:block;font-size:9px;color:#444;">Nombre completo</span>
                    <strong>{{ $consulta->nombre_completo }}</strong>
                </td>
                <td style="border:1px solid #000;padding:5px 10px;width:35%;">
                    <span style="display:block;font-size:9px;color:#444;">Teléfono</span>
                    <strong>{{ \App\Support\Telefono::formatear($consulta->telefono) ?: '—' }}</strong>
                </td>
            </tr>
            <tr>
                <td style="border:1px solid #000;padding:5px 10px;" colspan="3">
                    <span style="display:block;font-size:9px;color:#444;">Nota</span>
                    <strong>{{ $consulta->nota_interna ?: '—' }}</strong>
                </td>
            </tr>
        </table>

        <div style="background:#1a1a1a;color:#fff;font-weight:700;font-size:11px;padding:4px 10px;text-transform:uppercase;">Tipo de proceso</div>
        <table style="width:100%;border-collapse:collapse;margin-bottom:10px;font-size:12px;">
            <tr>
                <td style="border:1px solid #000;padding:5px 10px;width:50%;">
                    <span style="display:block;font-size:9px;color:#444;">Materia legal</span>
                    <strong>{{ $consulta->materia_legal }}</strong>
                </td>
                <td style="border:1px solid #000;padding:5px 10px;width:50%;">
                    <span style="display:block;font-size:9px;color:#444;">Tipo de proceso</span>
                    <strong>{{ $consulta->delito?->delito ?? $consulta->delito_texto ?? '—' }}</strong>
                </td>
            </tr>
        </table>

        @forelse ($citas as $indice => $cita)
            <div style="background:#1a1a1a;color:#fff;font-weight:700;font-size:11px;padding:4px 10px;text-transform:uppercase;">
                {{ $indice === 0 ? 'Agendar' : 'Reprogramación '.$indice }}
                — Abogado(a): {{ $cita->personal->pluck('nombre_completo')->join(', ') ?: '—' }}
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <tr>
                    <td style="border:1px solid #000;padding:5px 10px;width:20%;"><span style="display:block;font-size:9px;color:#444;">Fecha</span><strong>{{ $cita->fecha?->format('d/m/Y') ?? '—' }}</strong></td>
                    <td style="border:1px solid #000;padding:5px 10px;width:20%;"><span style="display:block;font-size:9px;color:#444;">Hora</span><strong>{{ substr((string) $cita->hora, 0, 5) ?: '—' }}</strong></td>
                    <td style="border:1px solid #000;padding:5px 10px;width:20%;"><span style="display:block;font-size:9px;color:#444;">Bs. Iguala</span><strong>{{ $cita->bs_iguala !== null ? number_format((float) $cita->bs_iguala, 2) : '—' }}</strong></td>
                    <td style="border:1px solid #000;padding:5px 10px;width:20%;"><span style="display:block;font-size:9px;color:#444;">Bs. Anticipo</span><strong>{{ $cita->bs_anticipo !== null ? number_format((float) $cita->bs_anticipo, 2) : '—' }}</strong></td>
                    <td style="border:1px solid #000;padding:5px 10px;width:20%;"><span style="display:block;font-size:9px;color:#444;">% Comisión</span><strong>{{ $cita->porcentaje_comision !== null ? number_format((float) $cita->porcentaje_comision, 2).'%' : '—' }}</strong></td>
                </tr>
                <tr>
                    <td style="border:1px solid #000;padding:5px 10px;"><span style="display:block;font-size:9px;color:#444;">Bs. Comisión</span><strong>{{ $cita->bs_comision !== null ? number_format((float) $cita->bs_comision, 2) : '—' }}</strong></td>
                    <td style="border:1px solid #000;padding:5px 10px;" colspan="2"><span style="margin-right:3px;">{{ $cita->confirmada ? '☑' : '☐' }}</span>Cita confirmada</td>
                    <td style="border:1px solid #000;padding:5px 10px;" colspan="2"><span style="margin-right:3px;">{{ $cita->anulada ? '☑' : '☐' }}</span>Cita anulada</td>
                </tr>
                <tr>
                    <td style="border:1px solid #000;padding:5px 10px;" colspan="5">
                        <span style="display:block;font-size:9px;color:#444;">Servicios requeridos</span>
                        <span style="margin-right:3px;">{{ $cita->servicio_psicologia ? '☑' : '☐' }}</span>Psicología &nbsp;&nbsp;
                        <span style="margin-right:3px;">{{ $cita->servicio_trabajo_social ? '☑' : '☐' }}</span>Trabajo social &nbsp;&nbsp;
                        <span style="margin-right:3px;">{{ $cita->servicio_otro ? '☑' : '☐' }}</span>Otro{{ $cita->servicio_otro && $cita->servicio_otro_detalle ? ': '.$cita->servicio_otro_detalle : '' }}
                    </td>
                </tr>
                <tr>
                    <td style="border:1px solid #000;padding:5px 10px;" colspan="5">
                        <span style="display:block;font-size:9px;color:#444;">Nota</span>
                        <div style="white-space:pre-line;min-height:20px;">{{ $cita->detalle ?: '—' }}</div>
                    </td>
                </tr>
            </table>
            @if ($puedeEditar && ! $cita->anulada)
                <div style="display:flex;justify-content:flex-end;flex-wrap:wrap;gap:6px;margin:6px 0 14px;">
                    @unless ($cita->confirmada)
                        <x-filament::button color="success" size="xs" icon="heroicon-o-check-circle"
                            wire:click="mountAction('confirmarCita', { cita: {{ $cita->id }} })">
                            Confirmar
                        </x-filament::button>
                    @endunless
                    <x-filament::button color="danger" size="xs" icon="heroicon-o-x-circle"
                        wire:click="mountAction('anularCita', { cita: {{ $cita->id }} })">
                        Anular
                    </x-filament::button>
                    <x-filament::button color="warning" size="xs" icon="heroicon-o-arrow-path"
                        wire:click="mountAction('reagendarCita', { cita: {{ $cita->id }} })">
                        Reagendar
                    </x-filament::button>
                </div>
            @else
                <div style="margin-bottom:14px;"></div>
            @endif
        @empty
            <div style="background:#1a1a1a;color:#fff;font-weight:700;font-size:11px;padding:4px 10px;text-transform:uppercase;">Agendar</div>
            <table style="width:100%;border-collapse:collapse;margin-bottom:14px;font-size:12px;">
                <tr><td style="border:1px solid #000;padding:8px 10px;">Sin citas agendadas todavía. Usa "Ver ficha completa" para agendar la primera.</td></tr>
            </table>
        @endforelse

        <div style="background:#1a1a1a;color:#fff;font-weight:700;font-size:11px;padding:4px 10px;text-transform:uppercase;">Cerrar caso</div>
        <table style="width:100%;border-collapse:collapse;margin-bottom:4px;font-size:12px;">
            <tr>
                <td style="border:1px solid #000;padding:8px;">
                    <span style="display:block;font-size:9px;color:#444;margin-bottom:4px;">Motivo de cierre</span>
                    @if ($puedeCerrar)
                        <select wire:model="motivoCierre" style="font-size:12px;padding:4px 6px;border:1px solid #999;border-radius:4px;min-width:260px;">
                            <option value="">— Sin cambios —</option>
                            <option value="{{ \App\Filament\Admin\Resources\Consultas\Pages\FichaAgendamiento::OPCION_CLIENTE_EJECUTIVO }}">Cliente ejecutivo</option>
                            @foreach ($motivos as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                            <option value="{{ \App\Filament\Admin\Resources\Consultas\Pages\FichaAgendamiento::OPCION_CITA_ANULADA }}">Cita anulada (cita vigente)</option>
                        </select>
                    @else
                        <div>{{ $consulta->caso_id !== null ? 'Cliente ejecutivo' : $consulta->estado?->getLabel() }}
                            <span style="font-size:9px;color:#666;">
                                ({{ $consulta->caso_id !== null ? 'ya es Cliente Ejecutivo' : 'ya está cerrada' }}, no se puede volver a cerrar desde aquí)
                            </span>
                        </div>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="border:1px solid #000;padding:5px 8px;">
                    <span style="display:block;font-size:9px;color:#444;margin-bottom:2px;">Observación</span>
                    @if ($puedeEditar && $puedeCerrar)
                        <textarea wire:model="observacionCierre" rows="3" style="width:100%;font-family:inherit;font-size:12px;padding:4px 6px;border:1px solid #999;border-radius:4px;resize:vertical;"></textarea>
                    @else
                        <div style="white-space:pre-line;min-height:20px;">{{ $vigente?->observacion_cierre ?: '—' }}</div>
                    @endif
                </td>
            </tr>
        </table>

        @if ($puedeEditar && $puedeCerrar)
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
                <x-filament::button color="gray" wire:click="cancelarCierre">
                    Cancelar
                </x-filament::button>
                <x-filament::button color="primary" wire:click="guardarCierre">
                    Guardar
                </x-filament::button>
            </div>
        @endif
    </div>
</div>
