{{-- Arriba del asistente de costas y costos (EditCliente::anadirCasoAction()):
     cerrar el caso de origen sin iniciar las costas. --}}
<div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:.75rem 1rem;margin-bottom:.5rem;border:1px solid rgb(220 38 38 / .3);border-radius:.6rem;background:rgb(220 38 38 / .05);">
    <div style="font-size:.875rem;">
        <strong>Caso N° {{ $origen->id }}</strong> — pendiente de costas y costos.
        <span style="opacity:.75;">Si no se van a iniciar ahora, ciérralo con fecha y justificación.</span>
    </div>
    {{ $boton }}
</div>
