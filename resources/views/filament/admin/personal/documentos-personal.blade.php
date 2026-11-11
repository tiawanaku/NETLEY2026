@if ($documentos->isEmpty())
    <p style="font-size:14px;opacity:.7;margin:0;">No hay documentos adjuntos.</p>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.35);">
                    <th style="padding:8px 10px;font-weight:600;white-space:nowrap;">Fecha</th>
                    <th style="padding:8px 10px;font-weight:600;">Descripción</th>
                    <th style="padding:8px 10px;font-weight:600;">Documento</th>
                    <th style="padding:8px 10px;width:1%;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documentos as $documento)
                    <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                        <td style="padding:8px 10px;white-space:nowrap;vertical-align:middle;">
                            {{ ($documento->fecha ?? $documento->created_at)?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td style="padding:8px 10px;font-weight:600;vertical-align:middle;">
                            {{ $documento->nombre ?: '—' }}
                        </td>
                        <td style="padding:8px 10px;opacity:.75;vertical-align:middle;word-break:break-all;">
                            {{ basename($documento->ruta ?? '') }}
                        </td>
                        <td style="padding:8px 10px;white-space:nowrap;vertical-align:middle;">
                            <div style="display:flex;gap:6px;justify-content:flex-end;">
                                <x-filament::button color="gray" size="sm" icon="heroicon-o-arrow-down-tray"
                                    wire:click="mountAction('descargarDocumentoPersonal', { documento: {{ $documento->id }} })">
                                    Descargar
                                </x-filament::button>
                                @if ($puedeEditar)
                                    <x-filament::button color="warning" size="sm" icon="heroicon-o-pencil-square"
                                        wire:click="mountAction('editarDocumentoPersonal', { documento: {{ $documento->id }} })">
                                        Editar
                                    </x-filament::button>
                                    <x-filament::button color="danger" size="sm" icon="heroicon-o-trash"
                                        wire:click="mountAction('eliminarDocumentoPersonal', { documento: {{ $documento->id }} })">
                                        Eliminar
                                    </x-filament::button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
