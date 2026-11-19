@php
    // Mismos datos que el PDF (pdf/ficha-agendamiento.blade.php), con el
    // estilo del panel: tarjetas por sección (ver .netley-ficha en admin-theme.css).
    $reagendada = $citas->count() > 1;
    $vigente = $citas->where('anulada', false)->last() ?? $citas->last();
    $bs = fn ($monto) => $monto !== null ? 'Bs. '.number_format((float) $monto, fmod((float) $monto, 1.0) == 0.0 ? 0 : 2, '.', '') : '—';
    $porcentaje = fn ($valor) => $valor !== null ? rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.').' %' : '—';
    $tipoProceso = $consulta->delito?->delito ?? $consulta->delito_texto;
    $nota = collect([$vigente?->detalle, $consulta->nota_interna])->filter(fn ($texto) => filled($texto))->unique()->join("\n");
    $servicios = collect([
        'Psicología' => $vigente?->servicio_psicologia,
        'Social' => $vigente?->servicio_trabajo_social,
        'Otros'.($vigente?->servicio_otro && $vigente->servicio_otro_detalle ? ': '.$vigente->servicio_otro_detalle : '') => $vigente?->servicio_otro,
    ]);
@endphp

<div class="netley-ficha">
    {{-- Encabezado: logo · título · placa con el N° de consulta --}}
    <header class="netley-ficha-encabezado">
        <img src="{{ asset('images/netley-logo-ficha.png') }}" alt="NETLEY" class="netley-ficha-logo">

        <div class="netley-ficha-cabecera">
            <span class="netley-ficha-sobretitulo">Ficha de agendamiento</span>
            <h2 class="netley-ficha-titulo">Consultas directas{{ $reagendada ? ' reagendadas' : '' }} {{ ($consulta->created_at ?? now())->format('Y') }}</h2>
            <span class="netley-ficha-subtitulo">{{ $consulta->nombre_completo }}</span>
        </div>

        <div class="netley-ficha-numero">
            <span class="netley-ficha-numero-etiqueta">Consulta</span>
            <span class="netley-ficha-numero-valor"><small>N°</small>{{ str_pad((string) $consulta->id, 4, '0', STR_PAD_LEFT) }}</span>
            <span class="netley-ficha-numero-pie">{{ ($consulta->fecha_registro ?? $consulta->created_at)?->format('d/m/Y') }}</span>
        </div>
    </header>

    {{-- Ingreso --}}
    <section class="netley-ficha-seccion">
        <h3>Ingreso</h3>
        <dl class="netley-ficha-datos">
            <div><dt>Fecha de ingreso</dt><dd>{{ ($consulta->fecha_registro ?? $consulta->created_at)?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt>Ciudad</dt><dd>{{ $consulta->ciudad ?: '—' }}</dd></div>
            <div><dt>Agendado por</dt><dd>{{ $consulta->atendidoPor?->nombre_completo ?: '—' }}</dd></div>
            <div><dt>Forma de ingreso</dt><dd>{{ $consulta->forma_ingreso?->getLabel() ?? '—' }}</dd></div>
            <div><dt>Origen</dt><dd>{{ $consulta->origen ?: '—' }}</dd></div>
            <div><dt>Detalle</dt><dd>{{ $consulta->origen_detalle ?: '—' }}</dd></div>
        </dl>
    </section>

    {{-- Cliente y consulta --}}
    <section class="netley-ficha-seccion">
        <h3>Datos del cliente</h3>
        <dl class="netley-ficha-datos">
            <div><dt>N° de cliente</dt><dd>{{ $consulta->caso?->cliente?->nro_cliente ?? '—' }}</dd></div>
            <div><dt>Nombre</dt><dd>{{ $consulta->nombres ?: '—' }}</dd></div>
            <div><dt>Apellido</dt><dd>{{ trim($consulta->ap_paterno.' '.$consulta->ap_materno) ?: '—' }}</dd></div>
            <div><dt>Teléfono</dt><dd>{{ \App\Support\Telefono::formatear($consulta->telefono) ?: '—' }}</dd></div>
            <div><dt>Fecha de consulta</dt><dd>{{ $consulta->fecha_consulta?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt>Materia legal</dt><dd>{{ $consulta->materia_legal }}</dd></div>
            <div class="netley-ficha-ancho"><dt>Tipo de proceso</dt><dd>{{ $tipoProceso ?: '—' }}</dd></div>
        </dl>
    </section>

    {{-- Iguala y servicios --}}
    <section class="netley-ficha-seccion">
        <h3>Iguala profesional</h3>
        <dl class="netley-ficha-datos netley-ficha-montos">
            <div><dt>Iguala Netley</dt><dd>{{ $bs($vigente?->bs_iguala) }}</dd></div>
            <div><dt>Anticipo</dt><dd>{{ $bs($vigente?->bs_anticipo) }}</dd></div>
            <div><dt>Comisión</dt><dd>{{ $porcentaje($vigente?->porcentaje_comision) }}</dd></div>
            <div><dt>Comisión (Bs.)</dt><dd>{{ $bs($vigente?->bs_comision) }}</dd></div>
        </dl>
        <div class="netley-ficha-servicios">
            <span class="netley-ficha-etiqueta">Servicios requeridos</span>
            @foreach ($servicios as $nombre => $marcado)
                <span class="netley-ficha-chip {{ $marcado ? 'is-activo' : '' }}">
                    {{ $marcado ? '✓' : '' }} {{ $nombre }}
                </span>
            @endforeach
        </div>
        <div class="netley-ficha-nota">
            <span class="netley-ficha-etiqueta">Nota</span>
            <p>{!! filled($nota) ? nl2br(e($nota)) : '<span class="netley-ficha-vacio">Sin nota</span>' !!}</p>
        </div>
    </section>

    {{-- Citas: la primera "Agendar cita", las demás "Cita reagendada" --}}
    <section class="netley-ficha-seccion">
        <h3>Citas</h3>
        @forelse ($citas as $indice => $cita)
            <div class="netley-ficha-cita {{ $cita->anulada ? 'is-anulada' : '' }}">
                <div class="netley-ficha-cita-tipo">{{ $indice === 0 ? 'Agendar cita' : 'Cita reagendada' }}</div>
                <dl class="netley-ficha-cita-datos">
                    <div><dt>Abogado(a)</dt><dd>{{ $cita->personal->pluck('nombre_completo')->join(', ') ?: '—' }}</dd></div>
                    <div><dt>Fecha</dt><dd>{{ $cita->fecha?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt>Hora</dt><dd>{{ substr((string) $cita->hora, 0, 5) ?: '—' }}</dd></div>
                </dl>
                <div class="netley-ficha-cita-estado">
                    @if ($cita->anulada)
                        <span class="netley-ficha-badge is-peligro">Anulada</span>
                    @elseif ($cita->confirmada)
                        <span class="netley-ficha-badge is-exito">Confirmada</span>
                    @else
                        <span class="netley-ficha-badge is-aviso">Por confirmar</span>
                    @endif
                </div>
                @if ($puedeEditar && ! $cita->anulada)
                    <div class="netley-ficha-cita-acciones">
                        @unless ($cita->confirmada)
                            <x-filament::button color="success" size="xs" icon="heroicon-o-check-circle"
                                wire:click="mountAction('confirmarCita', { cita: {{ $cita->id }} })">
                                Confirmar
                            </x-filament::button>
                        @endunless
                        <x-filament::button color="danger" size="xs" outlined icon="heroicon-o-x-circle"
                            wire:click="mountAction('anularCita', { cita: {{ $cita->id }} })">
                            Anular
                        </x-filament::button>
                        <x-filament::button color="warning" size="xs" outlined icon="heroicon-o-arrow-path"
                            wire:click="mountAction('reagendarCita', { cita: {{ $cita->id }} })">
                            Reagendar
                        </x-filament::button>
                    </div>
                @endif
            </div>
        @empty
            <p class="netley-ficha-vacio">Sin citas agendadas todavía. Usa "Ver ficha completa" para agendar la primera.</p>
        @endforelse
    </section>

    {{-- Cerrar caso --}}
    <section class="netley-ficha-seccion">
        <h3>Cerrar caso</h3>
        <div class="netley-ficha-cierre">
            <label class="netley-ficha-campo">
                <span class="netley-ficha-etiqueta">Motivo de cierre</span>
                @if ($puedeCerrar)
                    <select wire:model="motivoCierre" class="netley-ficha-control">
                        <option value="">— Sin cambios —</option>
                        <option value="{{ \App\Filament\Admin\Resources\Consultas\Pages\FichaAgendamiento::OPCION_CLIENTE_EJECUTIVO }}">Cliente ejecutivo</option>
                        @foreach ($motivos as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                        <option value="{{ \App\Filament\Admin\Resources\Consultas\Pages\FichaAgendamiento::OPCION_CITA_ANULADA }}">Cita anulada (cita vigente)</option>
                    </select>
                @else
                    <div class="netley-ficha-valor">
                        {{ $consulta->caso_id !== null ? 'Cliente ejecutivo' : $consulta->estado?->getLabel() }}
                        <span class="netley-ficha-vacio">({{ $consulta->caso_id !== null ? 'ya es Cliente Ejecutivo' : 'ya está cerrada' }}, no se puede volver a cerrar desde aquí)</span>
                    </div>
                @endif
            </label>
            <label class="netley-ficha-campo">
                <span class="netley-ficha-etiqueta">Observación</span>
                @if ($puedeEditar && $puedeCerrar)
                    <textarea wire:model="observacionCierre" rows="3" class="netley-ficha-control"></textarea>
                @else
                    <div class="netley-ficha-valor" style="white-space:pre-line;">{{ $vigente?->observacion_cierre ?: '—' }}</div>
                @endif
            </label>
        </div>

        @if ($puedeEditar && $puedeCerrar)
            <div class="netley-ficha-pie">
                <x-filament::button color="gray" wire:click="cancelarCierre">Cancelar</x-filament::button>
                <x-filament::button color="primary" wire:click="guardarCierre">Guardar</x-filament::button>
            </div>
        @endif
    </section>

    {{-- Firma --}}
    <div class="netley-ficha-firma">
        <div class="netley-ficha-firma-linea"></div>
        <strong>{{ $vigente?->personal->pluck('nombre_completo')->join(', ') ?: '—' }}</strong>
        <span>Abogado responsable</span>
    </div>
</div>
