<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Caso;
use App\Models\DocumentoCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoController extends Controller
{
    /**
     * El cliente sube un documento a su propio expediente. Queda en la misma
     * tabla que usa el staff (documentos_cliente), marcado como subido por
     * el cliente, visible de inmediato en el Resource de Casos/Clientes.
     */
    public function store(Request $request, Caso $caso): RedirectResponse
    {
        $cliente = Auth::guard('cliente')->user();

        abort_unless($caso->cliente_id === $cliente->id, 403);

        $data = $request->validate([
            'descripcion' => ['nullable', 'string', 'max:100'],
            'archivo' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $ruta = $request->file('archivo')->store('documentos-clientes', 'local');

        DocumentoCliente::create([
            'cliente_id' => $cliente->id,
            'caso_id' => $caso->id,
            'ruta' => $ruta,
            'descripcion' => $data['descripcion'] ?: $request->file('archivo')->getClientOriginalName(),
            'subido_por_cliente' => true,
            'fecha_origen' => now(),
        ]);

        return back()
            ->with('documento_subido', true)
            ->with('documento_subido_caso', $caso->id)
            ->withFragment('caso-'.$caso->id);
    }

    public function descargar(DocumentoCliente $documento): StreamedResponse
    {
        $cliente = Auth::guard('cliente')->user();

        abort_unless($documento->cliente_id === $cliente->id, 403);

        return Storage::disk('local')->download($documento->ruta, $documento->descripcion ?: 'documento');
    }
}
