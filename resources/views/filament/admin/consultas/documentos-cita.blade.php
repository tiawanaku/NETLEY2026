@if (empty($documentos))
    <p style="font-size:14px;opacity:.7;margin:0;">No hay documentos adjuntos.</p>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.35);">
                    <th style="padding:8px 10px;font-weight:600;">Descripción</th>
                    <th style="padding:8px 10px;font-weight:600;">Documento</th>
                    <th style="padding:8px 10px;width:1%;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documentos as $indice => $documento)
                    <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                        <td style="padding:8px 10px;font-weight:600;vertical-align:middle;">
                            {{ ($documento['descripcion'] ?? null) ?: '—' }}
                        </td>
                        <td style="padding:8px 10px;opacity:.75;vertical-align:middle;word-break:break-all;">
                            {{ basename($documento['ruta'] ?? '') }}
                        </td>
                        <td style="padding:8px 10px;text-align:right;white-space:nowrap;vertical-align:middle;">
                            <x-filament::button color="gray" size="sm" icon="heroicon-o-arrow-down-tray"
                                wire:click="mountAction('descargarDocumentoCita', { indice: {{ $indice }} })">
                                Descargar
                            </x-filament::button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
