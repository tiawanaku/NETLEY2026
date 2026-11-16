<?php

namespace App\Http\Controllers\Portal;

use App\Enums\EstadoCuota;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $cliente = Auth::guard('cliente')->user();

        $casos = $cliente->casos()
            ->with([
                'delito',
                'seguimientos' => fn ($q) => $q->orderByDesc('fecha_seguimiento'),
                'citas' => fn ($q) => $q->where('anulada', false)
                    ->whereDate('fecha', '>=', now()->toDateString())
                    ->orderBy('fecha')
                    ->orderBy('hora'),
                'planesPago' => fn ($q) => $q->where('estado', EstadoCuota::Pendiente)->orderBy('fecha'),
                // Solo los documentos marcados visibles para el cliente (y los que subió él).
                'documentos' => fn ($q) => $q->where(fn ($w) => $w->where('visible_cliente', true)->orWhere('subido_por_cliente', true))
                    ->orderByDesc('fecha_origen'),
            ])
            ->orderByDesc('created_at')
            ->get();

        return view('portal.dashboard', [
            'cliente' => $cliente,
            'casos' => $casos,
        ]);
    }
}
