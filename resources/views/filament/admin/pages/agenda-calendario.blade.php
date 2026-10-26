<x-filament-panels::page>
    <style>
        @media (max-width: 820px) {
            .pista-scroll { display: block !important; }
        }
    </style>

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <x-filament::button color="gray" size="sm" wire:click="anterior">
                &larr;
            </x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="irHoy">
                Hoy
            </x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="siguiente">
                &rarr;
            </x-filament::button>
            <span style="font-size:18px;font-weight:600;color:#1a2537;text-transform:capitalize;margin-left:8px;">
                {{ $this->titulo }}
            </span>
        </div>

        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <div style="min-width:220px;">
                <x-filament::input.select wire:model.live="personalId">
                    <option value="">Todos los abogados</option>
                    @foreach ($this->abogados as $id => $nombre)
                        <option value="{{ $id }}">{{ $nombre }}</option>
                    @endforeach
                </x-filament::input.select>
            </div>

            <div style="display:inline-flex;border:1px solid #d1d5db;border-radius:8px;overflow:hidden;">
                @foreach (['mes' => 'Mes', 'semana' => 'Semana', 'dia' => 'Día'] as $valor => $etiqueta)
                    <button
                        type="button"
                        wire:click="setVista('{{ $valor }}')"
                        style="padding:6px 14px;font-size:13px;border:none;cursor:pointer;
                            background:{{ $vista === $valor ? '#1a2537' : '#fff' }};
                            color:{{ $vista === $valor ? '#fff' : '#374151' }};"
                    >
                        {{ $etiqueta }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:16px;font-size:12px;color:#374151;margin-bottom:10px;">
        <span style="display:inline-flex;align-items:center;gap:6px;">
            <span style="width:12px;height:12px;border-radius:3px;background:#dbeafe;border:1px solid #93c5fd;display:inline-block;"></span>
            Consulta
        </span>
        <span style="display:inline-flex;align-items:center;gap:6px;">
            <span style="width:12px;height:12px;border-radius:3px;background:#dcfce7;border:1px solid #86efac;display:inline-block;"></span>
            Cliente Ejecutivo
        </span>
    </div>

    @php
        $pintarCita = function ($cita) {
            $esClienteEjecutivo = $cita->caso_id !== null;
            $nombre = $esClienteEjecutivo
                ? ($cita->caso?->cliente?->nombre_completo ?? 'Caso #'.$cita->caso_id)
                : ($cita->consulta?->nombre_completo ?? 'Consulta');
            $hora = $cita->hora instanceof \Illuminate\Support\Carbon
                ? $cita->hora->format('H:i')
                : \Illuminate\Support\Carbon::parse((string) $cita->hora)->format('H:i');
            $abogados = $cita->personal->map(fn ($p) => $p->nombres.' '.$p->ap_paterno)->implode(', ');

            return [
                'fondo' => $esClienteEjecutivo ? '#dcfce7' : '#dbeafe',
                'borde' => $esClienteEjecutivo ? '#86efac' : '#93c5fd',
                'texto' => $esClienteEjecutivo ? '#166534' : '#1e40af',
                'nombre' => $nombre,
                'hora' => $hora,
                'abogados' => $abogados,
                'url' => \App\Filament\Admin\Resources\Citas\CitaResource::getUrl('edit', ['record' => $cita->id]),
            ];
        };
    @endphp

    @if ($vista === 'mes')
        <p class="pista-scroll" style="display:none;font-size:12px;color:#9ca3af;margin:0 0 6px;">
            Desliza el calendario hacia los lados para ver toda la semana →
        </p>
        <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:8px;">
            <div style="display:grid;grid-template-columns:repeat(7,minmax(110px,1fr));gap:1px;background:#e5e7eb;min-width:700px;">
                @foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $diaNombre)
                    <div style="background:#1a2537;color:#fff;padding:8px;text-align:center;font-size:12px;font-weight:600;">
                        {{ $diaNombre }}
                    </div>
                @endforeach

                @foreach ($this->semanas as $semana)
                    @foreach ($semana as $dia)
                        <div style="background:{{ $dia['esHoy'] ? '#eff6ff' : '#fff' }};min-height:110px;padding:6px;{{ $dia['enMes'] ? '' : 'opacity:.4;' }}">
                            <div style="font-size:12px;font-weight:{{ $dia['esHoy'] ? '700' : '600' }};color:{{ $dia['esHoy'] ? '#2563eb' : '#374151' }};margin-bottom:4px;">
                                {{ $dia['fecha']->day }}
                            </div>

                            @foreach ($dia['citas'] as $cita)
                                @php $info = $pintarCita($cita); @endphp
                                <a
                                    href="{{ $info['url'] }}"
                                    style="display:block;font-size:10px;line-height:1.3;background:{{ $info['fondo'] }};border:1px solid {{ $info['borde'] }};color:{{ $info['texto'] }};border-radius:4px;padding:2px 4px;margin-bottom:2px;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                    title="{{ $info['hora'].' — '.$info['nombre'].($info['abogados'] ? ' ('.$info['abogados'].')' : '') }}"
                                >
                                    {{ $info['hora'] }} {{ $info['nombre'] }}
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    @elseif ($vista === 'semana')
        <p class="pista-scroll" style="display:none;font-size:12px;color:#9ca3af;margin:0 0 6px;">
            Desliza el calendario hacia los lados para ver toda la semana →
        </p>
        <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:8px;">
            <div style="display:grid;grid-template-columns:repeat(7,minmax(140px,1fr));gap:1px;background:#e5e7eb;min-width:900px;">
                @foreach ($this->diasSemana as $dia)
                    <div style="background:{{ $dia['esHoy'] ? '#eff6ff' : '#fff' }};min-height:320px;padding:6px;">
                        <div style="font-size:12px;font-weight:{{ $dia['esHoy'] ? '700' : '600' }};color:{{ $dia['esHoy'] ? '#2563eb' : '#374151' }};margin-bottom:6px;text-transform:capitalize;">
                            {{ $dia['fecha']->translatedFormat('D d') }}
                        </div>

                        @forelse ($dia['citas'] as $cita)
                            @php $info = $pintarCita($cita); @endphp
                            <a
                                href="{{ $info['url'] }}"
                                style="display:block;font-size:11px;line-height:1.35;background:{{ $info['fondo'] }};border:1px solid {{ $info['borde'] }};color:{{ $info['texto'] }};border-radius:4px;padding:4px 6px;margin-bottom:4px;text-decoration:none;"
                            >
                                <div style="font-weight:600;">{{ $info['hora'] }}</div>
                                <div>{{ $info['nombre'] }}</div>
                                @if ($info['abogados'])
                                    <div style="opacity:.75;">{{ $info['abogados'] }}</div>
                                @endif
                            </a>
                        @empty
                            <div style="font-size:11px;color:#9ca3af;">—</div>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            @forelse ($this->citasDelDia as $cita)
                @php $info = $pintarCita($cita); @endphp
                <a
                    href="{{ $info['url'] }}"
                    style="display:flex;align-items:center;gap:16px;padding:12px 16px;text-decoration:none;border-bottom:1px solid #f3f4f6;background:#fff;"
                >
                    <div style="width:60px;font-size:14px;font-weight:700;color:{{ $info['texto'] }};flex-shrink:0;">
                        {{ $info['hora'] }}
                    </div>
                    <div style="flex-grow:1;">
                        <div style="font-size:14px;font-weight:600;color:#1a2537;">{{ $info['nombre'] }}</div>
                        @if ($info['abogados'])
                            <div style="font-size:12px;color:#6b7280;">Abogado(s): {{ $info['abogados'] }}</div>
                        @endif
                        @if ($cita->detalle)
                            <div style="font-size:12px;color:#6b7280;">{{ $cita->detalle }}</div>
                        @endif
                    </div>
                    <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:9999px;background:{{ $info['fondo'] }};color:{{ $info['texto'] }};border:1px solid {{ $info['borde'] }};flex-shrink:0;">
                        {{ $cita->caso_id !== null ? 'Cliente Ejecutivo' : 'Consulta' }}
                    </span>
                </a>
            @empty
                <div style="padding:24px;text-align:center;color:#9ca3af;font-size:13px;">
                    No hay citas agendadas este día.
                </div>
            @endforelse
        </div>
    @endif
</x-filament-panels::page>
