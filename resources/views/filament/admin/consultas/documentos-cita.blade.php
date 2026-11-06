<div style="display:flex;flex-direction:column;gap:10px;">
    @if (empty($documentos))
        <p style="font-size:14px;opacity:.7;margin:0;">No hay documentos adjuntos.</p>
    @else
        @foreach ($documentos as $indice => $documento)
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid rgba(128,128,128,.25);border-radius:10px;padding:10px 14px;">
                <div style="min-width:0;">
                    <div style="font-weight:600;font-size:14px;">
                        {{ $documento['descripcion'] ?: basename($documento['ruta'] ?? '') }}
                    </div>
                    <div style="font-size:12px;opacity:.6;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        {{ basename($documento['ruta'] ?? '') }}
                    </div>
                </div>
                <x-filament::button color="gray" size="sm" icon="heroicon-o-arrow-down-tray"
                    wire:click="mountAction('descargarDocumentoCita', { indice: {{ $indice }} })">
                    Descargar
                </x-filament::button>
            </div>
        @endforeach
    @endif
</div>
