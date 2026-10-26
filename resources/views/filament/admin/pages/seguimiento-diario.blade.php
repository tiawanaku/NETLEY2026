<x-filament-panels::page>
    <style>
        @media (max-width: 780px) {
            .pista-scroll { display: block !important; }
        }
    </style>

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
        <p style="font-size:13px;color:#6b7280;margin:0;">
            Marca los casos vigentes que revisaste hoy y usa "Redactar informe diario" para dejar una nota de
            seguimiento en cada uno.
        </p>
        <div style="display:flex;gap:8px;">
            <x-filament::button color="gray" size="sm" wire:click="marcarTodos">
                Marcar todos
            </x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="desmarcarTodos">
                Desmarcar todos
            </x-filament::button>
        </div>
    </div>

    <p class="pista-scroll" style="display:none;font-size:12px;color:#9ca3af;margin:0 0 6px;">
        Desliza la tabla hacia los lados para ver todas las columnas →
    </p>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow-x:auto;">
        @if ($this->casos->isEmpty())
            <div style="padding:40px;text-align:center;color:#6b7280;font-size:14px;">
                No tienes casos vigentes asignados.
            </div>
        @else
            <table style="width:100%;min-width:760px;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f9fafb;">
                        <th style="width:40px;padding:10px 14px;"></th>
                        <th style="text-align:left;padding:10px 14px;color:#374151;font-weight:600;">Caso</th>
                        <th style="text-align:left;padding:10px 14px;color:#374151;font-weight:600;">Cliente</th>
                        <th style="text-align:left;padding:10px 14px;color:#374151;font-weight:600;">Materia</th>
                        <th style="text-align:left;padding:10px 14px;color:#374151;font-weight:600;">Estado</th>
                        <th style="text-align:center;padding:10px 14px;color:#374151;font-weight:600;">Fojas</th>
                        <th style="text-align:left;padding:10px 14px;color:#374151;font-weight:600;">Seguimiento hoy</th>
                        <th style="padding:10px 14px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->casos as $caso)
                        <tr wire:key="caso-{{ $caso->id }}" style="border-top:1px solid #f3f4f6;">
                            <td style="padding:10px 14px;">
                                <input type="checkbox" wire:model="seleccionados" value="{{ $caso->id }}" style="width:16px;height:16px;">
                            </td>
                            <td style="padding:10px 14px;font-weight:600;color:#1a2537;">#{{ $caso->id }}</td>
                            <td style="padding:10px 14px;">{{ $caso->cliente?->nombre_completo ?? 'Sin cliente' }}</td>
                            <td style="padding:10px 14px;">{{ $caso->materia_legal }}</td>
                            <td style="padding:10px 14px;">
                                <span style="background:#fef3c7;color:#92400e;font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;">
                                    {{ $caso->estado->getLabel() }}
                                </span>
                            </td>
                            <td style="padding:10px 14px;text-align:center;color:#374151;">
                                {{ $this->fojasDe($caso->id) }}
                            </td>
                            <td style="padding:10px 14px;">
                                @if ($this->tieneSeguimientoHoy($caso->id))
                                    <div>
                                        <span style="background:#dcfce7;color:#166534;font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;">
                                            Sí &mdash; {{ $this->responsableHoyDe($caso->id) }}
                                        </span>
                                        <div style="font-size:11px;color:#9ca3af;margin-top:3px;">
                                            {{ $this->tieneObservacionesHoy($caso->id) ? 'Con observaciones' : 'Sin observaciones' }}
                                        </div>
                                    </div>
                                @else
                                    <span style="background:#f3f4f6;color:#6b7280;font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;">
                                        No
                                    </span>
                                @endif
                            </td>
                            <td style="padding:10px 14px;white-space:nowrap;">
                                <div style="display:flex;gap:6px;">
                                    <x-filament::button tag="a" href="{{ $this->urlDocumentos($caso->id) }}" color="gray" size="xs" icon="heroicon-o-folder-open">
                                        Documentos
                                    </x-filament::button>
                                    <x-filament::button wire:click="abrirInformeCaso({{ $caso->id }})" color="gray" size="xs" icon="heroicon-o-document-text">
                                        Informe del caso
                                    </x-filament::button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>
