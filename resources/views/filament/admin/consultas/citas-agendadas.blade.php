<div style="display:flex;flex-direction:column;gap:12px;">
    @if ($citas->isEmpty())
        <p style="font-size:14px;opacity:.7;margin:0;">No hay citas agendadas para esta consulta.</p>
    @else
        @foreach ($citas as $cita)
            <div style="border:1px solid rgba(128,128,128,.25);border-radius:12px;padding:14px 16px;">
                <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;">
                    <div style="font-weight:700;font-size:15px;">
                        {{ $cita->fecha?->format('d/m/Y') }} &middot; {{ substr((string) $cita->hora, 0, 5) }}
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <span style="font-size:12px;font-weight:600;padding:2px 10px;border-radius:999px;{{ $cita->anulada ? 'background:#fee2e2;color:#991b1b;' : 'background:#dcfce7;color:#166534;' }}">
                            {{ $cita->anulada ? 'Anulada' : 'Vigente' }}
                        </span>
                        @if (! $cita->anulada)
                            <span style="font-size:12px;font-weight:600;padding:2px 10px;border-radius:999px;{{ $cita->confirmada ? 'background:#dbeafe;color:#1e40af;' : 'background:#fef9c3;color:#854d0e;' }}">
                                {{ $cita->confirmada ? 'Cita confirmada' : 'Por confirmar' }}
                            </span>
                        @endif
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px 16px;font-size:14px;">
                    <div>
                        <div style="font-size:12px;opacity:.6;margin-bottom:2px;">Abogado(s)</div>
                        <div>{{ $cita->personal->pluck('nombres')->join(', ') ?: '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;opacity:.6;margin-bottom:2px;">Bs. Iguala</div>
                        <div>{{ $cita->bs_iguala !== null ? 'Bs. '.number_format((float) $cita->bs_iguala, 2) : '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;opacity:.6;margin-bottom:2px;">Bs. Anticipo</div>
                        <div>{{ $cita->bs_anticipo !== null ? 'Bs. '.number_format((float) $cita->bs_anticipo, 2) : '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;opacity:.6;margin-bottom:2px;">% Comisión</div>
                        <div>{{ $cita->porcentaje_comision !== null ? number_format((float) $cita->porcentaje_comision, 2).'%' : '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;opacity:.6;margin-bottom:2px;">Bs. Comisión</div>
                        <div>{{ $cita->bs_comision !== null ? 'Bs. '.number_format((float) $cita->bs_comision, 2) : '—' }}</div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px 16px;font-size:14px;margin-top:10px;">
                    <div>
                        <div style="font-size:12px;opacity:.6;margin-bottom:2px;">Servicios requeridos</div>
                        <div>{{ $cita->servicios_requeridos ?: '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;opacity:.6;margin-bottom:2px;">Nota</div>
                        <div style="white-space:pre-line;">{{ $cita->detalle ?: '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;opacity:.6;margin-bottom:2px;">Observaciones</div>
                        <div style="white-space:pre-line;">{{ $cita->observaciones ?: '—' }}</div>
                    </div>
                </div>

                @if ($puedeEditar && ! $cita->anulada)
                    <div style="display:flex;justify-content:flex-end;flex-wrap:wrap;gap:8px;margin-top:12px;">
                        @unless ($cita->confirmada)
                            <x-filament::button color="success" size="sm" icon="heroicon-o-check-circle"
                                wire:click="mountAction('confirmarCita', { cita: {{ $cita->id }} })">
                                Confirmar
                            </x-filament::button>
                        @endunless
                        <x-filament::button color="danger" size="sm" icon="heroicon-o-x-circle"
                            wire:click="mountAction('anularCita', { cita: {{ $cita->id }} })">
                            Anular
                        </x-filament::button>
                        <x-filament::button color="warning" size="sm" icon="heroicon-o-arrow-path"
                            wire:click="mountAction('reagendarCita', { cita: {{ $cita->id }} })">
                            Reagendar
                        </x-filament::button>
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>
