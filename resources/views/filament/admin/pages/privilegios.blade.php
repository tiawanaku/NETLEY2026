<x-filament-panels::page>
    <style>
        @media (max-width: 780px) {
            .pista-scroll { display: block !important; }
        }
    </style>

    <div style="margin-bottom:16px;color:#374151;font-size:14px;">
        Master y Administrador siempre tienen acceso completo a todo el sistema. Desde esta tabla se
        otorga o se quita el acceso a cada sección para los demás roles. Un clic en el interruptor
        aplica el cambio de inmediato.
    </div>

    <p class="pista-scroll" style="display:none;font-size:12px;color:#9ca3af;margin:0 0 6px;">
        Desliza la tabla hacia los lados para ver todos los roles →
    </p>

    <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:10px;">
        <table style="width:100%;min-width:720px;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#f9fafb;">
                    <th style="text-align:left;padding:10px 14px;border-bottom:1px solid #e5e7eb;color:#1a2537;font-weight:600;white-space:nowrap;">
                        Sección
                    </th>
                    <th style="text-align:center;padding:10px 14px;border-bottom:1px solid #e5e7eb;color:#166534;font-weight:600;white-space:nowrap;">
                        Master
                    </th>
                    <th style="text-align:center;padding:10px 14px;border-bottom:1px solid #e5e7eb;color:#166534;font-weight:600;white-space:nowrap;">
                        Administrador
                    </th>
                    @foreach ($this->rolesConfigurables as $rol)
                        <th style="text-align:center;padding:10px 14px;border-bottom:1px solid #e5e7eb;color:#1a2537;font-weight:600;white-space:nowrap;">
                            {{ $rol->getLabel() }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($this->claves as $clave => $etiqueta)
                    <tr wire:key="fila-{{ $clave }}" style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:10px 14px;color:#1a2537;font-weight:500;white-space:nowrap;">
                            {{ $etiqueta }}
                        </td>
                        <td style="text-align:center;padding:10px 14px;">
                            <span title="Master siempre tiene acceso" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#22c55e;"></span>
                        </td>
                        <td style="text-align:center;padding:10px 14px;">
                            <span title="Administrador siempre tiene acceso" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#22c55e;"></span>
                        </td>
                        @foreach ($this->rolesConfigurables as $rol)
                            @php $concedido = $this->tienePermiso($rol, $clave); @endphp
                            <td style="text-align:center;padding:10px 14px;">
                                <button
                                    type="button"
                                    wire:click="alternar({{ $rol->value }}, '{{ $clave }}')"
                                    title="{{ $concedido ? 'Quitar acceso' : 'Otorgar acceso' }}"
                                    style="width:40px;height:22px;border-radius:999px;border:none;cursor:pointer;position:relative;
                                        background:{{ $concedido ? '#16a34a' : '#d1d5db' }};transition:background .15s;"
                                >
                                    <span style="position:absolute;top:2px;left:{{ $concedido ? '20px' : '2px' }};width:18px;height:18px;border-radius:50%;background:#fff;transition:left .15s;box-shadow:0 1px 2px rgba(0,0,0,.3);"></span>
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
