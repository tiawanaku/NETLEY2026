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
                'documentos' => fn ($q) => $q->orderByDesc('fecha_origen'),
            ])
            ->orderByDesc('created_at')
            ->get();

        return view('portal.dashboard', [
            'cliente' => $cliente,
            'casos' => $casos,
        ]);
    }
}
