@extends('layouts.public')

@php
    $coloresEstado = [
        'warning' => ['bg' => '#fef3c7', 'fg' => '#92400e'],
        'info' => ['bg' => '#dbeafe', 'fg' => '#1e40af'],
        'success' => ['bg' => '#dcfce7', 'fg' => '#166534'],
    ];
@endphp

@section('nav')
    <span style="align-self:center;font-size:13px;color:#cbd5e1;">Hola, {{ $cliente->nombres }}</span>
    <form method="POST" action="{{ route('portal.logout') }}">
        @csrf
        <button type="submit" class="boton boton-contorno" style="font-family:inherit;">Cerrar sesión</button>
    </form>
@endsection

@section('contenido')
    <style>
        .tabs-casos {
            display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 24px;
            border-bottom: 1px solid #e5e7eb;
        }
        .tab-caso {
            display: flex; align-items: center; gap: 8px;
            background: transparent; border: none; cursor: pointer;
            padding: 12px 16px; font-size: 13px; font-weight: 600; color: #6b7280;
            border-bottom: 2px solid transparent; margin-bottom: -1px; font-family: inherit;
        }
        .tab-caso:hover { color: #1a2537; }
        .tab-caso.activo { color: #1a2537; border-bottom-color: #4fc3f7; }
        .tab-caso .dot { width: 8px; height: 8px; border-radius: 999px; flex-shrink: 0; }
        .panel-caso { display: none; }
        .panel-caso.activo { display: block; }
    </style>

    <section style="padding:40px 0 70px;">
        <div class="contenedor">
            <h1 style="font-size:22px;color:#1a2537;margin:0 0 4px;">El avance de tu proceso</h1>
            <p style="font-size:14px;color:#6b7280;margin:0 0 30px;">
                {{ $casos->count() }} {{ $casos->count() === 1 ? 'caso registrado' : 'casos registrados' }} a tu nombre.
            </p>

            @if ($casos->isNotEmpty())
                <div class="tabs-casos" role="tablist">
                    @foreach ($casos as $caso)
                        @php $colorTab = $coloresEstado[$caso->estado->getColor()] ?? ['bg' => '#e5e7eb', 'fg' => '#374151']; @endphp
                        <button type="button" class="tab-caso" data-tab="caso-{{ $caso->id }}" role="tab">
                            <span class="dot" style="background:{{ $colorTab['fg'] }};"></span>
                            Caso #{{ $caso->id }} — {{ $caso->materia_legal }}
                        </button>
                    @endforeach
                </div>
            @endif

            @forelse ($casos as $caso)
                @php $color = $coloresEstado[$caso->estado->getColor()] ?? ['bg' => '#e5e7eb', 'fg' => '#374151']; @endphp
                <div class="panel-caso" id="caso-{{ $caso->id }}" data-panel="caso-{{ $caso->id }}" style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:26px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                        <div>
                            <h2 style="font-size:17px;color:#1a2537;margin:0 0 4px;">
                                Caso #{{ $caso->id }} — {{ $caso->materia_legal }}
                            </h2>
                            <p style="font-size:13px;color:#6b7280;margin:0;">
                                {{ $caso->delito?->delito ?? $caso->delito_texto ?? 'Sin detalle registrado' }}
                            </p>
                        </div>
                        <span style="background:{{ $color['bg'] }};color:{{ $color['fg'] }};font-size:12px;font-weight:700;padding:6px 14px;border-radius:999px;white-space:nowrap;">
                            {{ $caso->estado->getLabel() }}
                        </span>
                    </div>

                    <h3 style="font-size:13px;color:#374151;margin:18px 0 10px;text-transform:uppercase;letter-spacing:.04em;">
                        Historial de avance
                    </h3>

                    @if ($caso->seguimientos->isEmpty())
                        <p style="font-size:13px;color:#9ca3af;margin:0;">
                            Aún no hay actualizaciones registradas para este caso.
                        </p>
                    @else
                        <div style="border-left:2px solid #e5e7eb;margin-left:6px;">
                            @foreach ($caso->seguimientos as $seguimiento)
                                <div style="padding:0 0 18px 20px;position:relative;">
                                    <span style="position:absolute;left:-7px;top:2px;width:12px;height:12px;border-radius:50%;background:#4fc3f7;border:2px solid #fff;"></span>
                                    <div style="font-size:12px;color:#9ca3af;margin-bottom:2px;">
                                        {{ $seguimiento->fecha_seguimiento?->translatedFormat('d \d\e F, Y') }}
                                    </div>
                                    <div style="font-size:14px;font-weight:600;color:#1a2537;">
                                        {{ $seguimiento->etapa_proceso }}
                                    </div>
                                    @if ($seguimiento->observaciones)
                                        <div style="font-size:13px;color:#4b5563;margin-top:2px;">
                                            {{ $seguimiento->observaciones }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;margin-top:24px;padding-top:20px;border-top:1px solid #f3f4f6;">
                        <div>
                            <h3 style="font-size:13px;color:#374151;margin:0 0 10px;text-transform:uppercase;letter-spacing:.04em;">
                                Citas pendientes
                            </h3>
                            @if ($caso->citas->isEmpty())
                                <p style="font-size:13px;color:#9ca3af;margin:0;">No tienes citas próximas para este caso.</p>
                            @else
                                @foreach ($caso->citas as $cita)
                                    <div style="display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid #f3f4f6;">
                                        <div style="background:#eff6ff;color:#1e40af;border-radius:8px;padding:6px 10px;text-align:center;min-width:56px;">
                                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;">{{ $cita->fecha->translatedFormat('M') }}</div>
                                            <div style="font-size:15px;font-weight:700;">{{ $cita->fecha->format('d') }}</div>
                                        </div>
                                        <div>
                                            <div style="font-size:13px;font-weight:600;color:#1a2537;">
                                                {{ $cita->detalle ?: ($cita->tipo ?: 'Cita programada') }}
                                            </div>
                                            @if ($cita->hora)
                                                <div style="font-size:12px;color:#6b7280;">{{ \Illuminate\Support\Carbon::parse($cita->hora)->format('H:i') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>

                        <div>
                            <h3 style="font-size:13px;color:#374151;margin:0 0 10px;text-transform:uppercase;letter-spacing:.04em;">
                                Pagos programados
                            </h3>
                            @if ($caso->planesPago->isEmpty())
                                <p style="font-size:13px;color:#9ca3af;margin:0;">No tienes cuotas pendientes para este caso.</p>
                            @else
                                @foreach ($caso->planesPago as $cuota)
                                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f3f4f6;">
                                        <div>
                                            <div style="font-size:13px;font-weight:600;color:#1a2537;">
                                                {{ $cuota->numero === 0 ? 'Anticipo' : 'Cuota '.$cuota->numero }}
                                            </div>
                                            <div style="font-size:12px;color:#6b7280;">
                                                Vence el {{ $cuota->fecha?->translatedFormat('d \d\e F, Y') }}
                                            </div>
                                        </div>
                                        <div style="font-size:14px;font-weight:700;color:#1a2537;">
                                            Bs. {{ number_format((float) $cuota->monto, 2) }}
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <div style="margin-top:24px;padding-top:20px;border-top:1px solid #f3f4f6;">
                        <h3 style="font-size:13px;color:#374151;margin:0 0 10px;text-transform:uppercase;letter-spacing:.04em;">
                            Documentos del expediente
                        </h3>

                        @if (session('documento_subido') && (int) session('documento_subido_caso') === $caso->id)
                            <div class="banner-exito" style="margin-bottom:14px;">
                                Documento subido correctamente. Nuestro equipo lo revisará junto al resto del expediente.
                            </div>
                        @endif

                        @if ($errors->has('archivo') && old('_caso_id') == $caso->id)
                            <div class="banner-error" style="margin-bottom:14px;">{{ $errors->first('archivo') }}</div>
                        @endif

                        @if ($caso->documentos->isEmpty())
                            <p style="font-size:13px;color:#9ca3af;margin:0 0 14px;">Todavía no hay documentos cargados para este caso.</p>
                        @else
                            <div style="margin-bottom:14px;">
                                @foreach ($caso->documentos as $documento)
                                    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:8px;padding:10px 0;border-bottom:1px solid #f3f4f6;">
                                        <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                                            <span style="width:32px;height:32px;border-radius:8px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#6b7280" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                                            </span>
                                            <div style="min-width:0;">
                                                <div style="font-size:13px;font-weight:600;color:#1a2537;overflow-wrap:break-word;">{{ $documento->descripcion ?: 'Documento' }}</div>
                                                <div style="font-size:11px;color:#9ca3af;">
                                                    {{ $documento->fecha_origen?->translatedFormat('d \d\e F, Y') }}
                                                    @if ($documento->subido_por_cliente) &middot; Subido por ti @endif
                                                </div>
                                            </div>
                                        </div>
                                        <a href="{{ route('portal.documentos.descargar', $documento) }}" style="font-size:12px;font-weight:600;color:#1e40af;flex-shrink:0;">Descargar</a>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('portal.documentos.store', $caso) }}" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                            @csrf
                            <input type="hidden" name="_caso_id" value="{{ $caso->id }}">
                            <div style="flex:1;min-width:180px;">
                                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Descripción (opcional)</label>
                                <input type="text" name="descripcion" placeholder="Ej. Cédula de identidad" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                            </div>
                            <div style="flex:1;min-width:200px;">
                                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Archivo (PDF, imagen o Word, máx. 10MB)</label>
                                <input type="file" name="archivo" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="width:100%;font-size:12px;">
                            </div>
                            <button type="submit" class="boton boton-primario" style="border:none;font-size:13px;padding:9px 18px;">
                                Subir documento
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:40px;text-align:center;color:#6b7280;font-size:14px;">
                    Todavía no tienes casos registrados a tu nombre.
                </div>
            @endforelse
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (function () {
            const tabs = document.querySelectorAll('.tab-caso');
            const panels = document.querySelectorAll('.panel-caso');
            if (! tabs.length) return;

            function activar(id) {
                tabs.forEach((t) => t.classList.toggle('activo', t.dataset.tab === id));
                panels.forEach((p) => p.classList.toggle('activo', p.dataset.panel === id));
            }

            tabs.forEach((t) => t.addEventListener('click', () => {
                activar(t.dataset.tab);
                history.replaceState(null, '', '#' + t.dataset.tab);
            }));

            const hash = location.hash.replace('#', '');
            const valido = hash && document.querySelector('[data-panel="' + hash + '"]');
            activar(valido ? hash : tabs[0].dataset.tab);
        })();
    </script>
@endpush
