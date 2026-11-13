{{--
    Arriba del Plan de pagos (ver PlanesPagoRelationManager::content()): la
    misma disposición del final del paso 3 de Cliente ejecutivo — "Resumen"
    a la izquierda y "Pago de servicios extra" al lado.
--}}
<div style="border:1px solid rgba(128,128,128,.25);border-radius:12px;padding:16px 18px;margin-bottom:16px;">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px 24px;align-items:start;">
        <div>
            <div style="font-size:14px;font-weight:500;margin-bottom:8px;">Resumen</div>
            <div style="overflow-x:auto;">{{ $resumen }}</div>
        </div>

        <fieldset style="border:1px solid rgba(128,128,128,.3);border-radius:10px;padding:10px 16px 14px;margin:0;">
            <legend style="font-size:14px;font-weight:500;padding:0 6px;">Pago de servicios extra</legend>

            @if (empty($servicios))
                <div style="font-size:13px;opacity:.65;">No se solicitaron servicios extra.</div>
            @else
                <div style="display:flex;flex-direction:column;gap:10px;">
                    @foreach ($servicios as $servicio)
                        <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px;align-items:center;">
                            <div style="font-size:14px;font-weight:500;">{{ $servicio['nombre'] }}</div>
                            <div style="font-size:14px;border:1px solid rgba(128,128,128,.3);border-radius:8px;padding:6px 10px;background:rgba(128,128,128,.06);">
                                {{ $servicio['monto'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </fieldset>
    </div>
</div>
