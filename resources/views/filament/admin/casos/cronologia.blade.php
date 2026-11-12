{{-- Línea de tiempo del caso (ver App\Support\CronologiaCaso y CronologiaRelationManager). --}}
<div style="border:1px solid rgba(128,128,128,.25);border-radius:12px;padding:18px 20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
        <div style="font-weight:700;font-size:15px;">Cronología del caso</div>
        <div style="font-size:12px;opacity:.65;">{{ $eventos->count() }} {{ $eventos->count() === 1 ? 'evento' : 'eventos' }} · del más antiguo al más reciente</div>
    </div>

    @if ($eventos->isEmpty())
        <p style="font-size:14px;opacity:.7;margin:0;">Todavía no hay movimientos registrados en este caso.</p>
    @else
        @php $hoyMarcado = false; @endphp
        <ol style="list-style:none;margin:0;padding:0;position:relative;">
            {{-- Línea vertical --}}
            <div style="position:absolute;left:17px;top:6px;bottom:6px;width:2px;background:rgba(128,128,128,.25);"></div>

            @foreach ($eventos as $evento)
                @if ($evento['futuro'] && ! $hoyMarcado)
                    @php $hoyMarcado = true; @endphp
                    <li style="position:relative;display:flex;align-items:center;gap:12px;margin:4px 0 16px;">
                        <div style="width:36px;display:flex;justify-content:center;flex-shrink:0;">
                            <div style="width:12px;height:12px;border-radius:999px;background:#f59e0b;box-shadow:0 0 0 4px rgba(245,158,11,.2);position:relative;"></div>
                        </div>
                        <div style="font-size:12px;font-weight:700;color:#d97706;text-transform:uppercase;letter-spacing:.5px;">Hoy · {{ today()->format('d/m/Y') }} — próximos</div>
                    </li>
                @endif

                <li style="position:relative;display:flex;gap:12px;margin-bottom:16px;{{ $evento['futuro'] ? 'opacity:.75;' : '' }}">
                    <div style="width:36px;height:36px;border-radius:999px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:{{ $evento['color'] }};color:#fff;position:relative;box-shadow:0 0 0 4px var(--color-white, #fff);">
                        <x-filament::icon :icon="$evento['icono']" style="width:18px;height:18px;" />
                    </div>
                    <div style="flex:1;min-width:0;padding-top:2px;">
                        <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 10px;">
                            <span style="font-weight:600;font-size:14px;">{{ $evento['titulo'] }}</span>
                            <span style="font-size:12px;opacity:.65;">
                                {{ $evento['fecha']->format('d/m/Y') }}{{ $evento['hora'] ? ' · '.$evento['hora'] : '' }}
                            </span>
                        </div>
                        @if ($evento['detalle'])
                            <div style="font-size:13px;opacity:.8;margin-top:2px;white-space:pre-line;word-break:break-word;">{{ $evento['detalle'] }}</div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
