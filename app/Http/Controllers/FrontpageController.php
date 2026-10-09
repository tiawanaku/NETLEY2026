<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAprobacion;
use App\Enums\EstadoCaso;
use App\Models\Caso;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\ImagenCarrusel;
use App\Models\Oficina;
use App\Models\Personal;
use App\Models\RedSocial;
use App\Models\Testimonio;
use App\Models\VideoPortada;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FrontpageController extends Controller
{
    public function index(Request $request): View
    {
        return view('frontpage.index', [
            'testimonios' => Testimonio::query()
                ->where('visible', true)
                ->where('estado', EstadoAprobacion::Aprobado)
                ->latest('fecha')
                ->limit(8)
                ->get(),
            'oficinas' => Oficina::query()->orderBy('ciudad')->get(),
            'imagenesCarrusel' => ImagenCarrusel::query()->where('activo', true)->orderBy('orden')->get(),
            'videos' => VideoPortada::query()->where('activo', true)->orderBy('orden')->get(),
            'redesSociales' => RedSocial::query()->where('activo', true)->orderBy('orden')->get(),
            'captcha' => $this->captcha($request),
            'stats' => [
                'casos' => Caso::query()->count(),
                'resueltos' => Caso::query()->where('estado', EstadoCaso::Cerrado)->count(),
                'clientes' => Cliente::query()->count(),
                'abogados' => Personal::query()->where('profesion', 'Abogado')->count(),
            ],
        ]);
    }

    /**
     * El testimonio público queda pendiente de moderación: entra a la misma
     * cola que ya gestiona el staff en /admin/testimonios (Aprobar/Rechazar).
     */
    public function storeTestimonio(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'correo' => ['nullable', 'email', 'max:100'],
            'calificacion' => ['required', 'integer', 'min:1', 'max:5'],
            'testimonio' => ['required', 'string', 'max:2000'],
        ]);

        Testimonio::create([
            ...$data,
            'estado' => EstadoAprobacion::Pendiente,
            'visible' => false,
            'fecha' => now(),
        ]);

        return redirect()->route('frontpage', ['t' => 'gracias'])
            ->with('testimonio_enviado', true)
            ->withFragment('opiniones');
    }

    public function storeConsulta(Request $request): RedirectResponse
    {
        $this->validarCaptcha($request);

        $data = $request->validate([
            'nombres' => ['required', 'string', 'max:40'],
            'ap_paterno' => ['nullable', 'string', 'max:30'],
            'telefono' => ['required', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:80'],
            'ciudad' => ['nullable', 'string', 'max:40'],
            'consulta' => ['required', 'string', 'max:3000'],
        ]);

        Consulta::create([
            ...$data,
            'fecha_consulta' => now(),
            'origen' => 'Sitio web',
        ]);

        $request->session()->forget(['captcha_a', 'captcha_b']);

        return redirect()->route('frontpage', ['c' => 'gracias'])
            ->with('consulta_enviada', true)
            ->withFragment('consulta');
    }

    /**
     * Captcha simple (suma de dos números) sin dependencias externas. Los
     * valores viven en sesión, se generan al cargar la página y se
     * regeneran en cada intento (correcto o no) para evitar reintentos.
     *
     * @return array{a: int, b: int}
     */
    protected function captcha(Request $request): array
    {
        if (! $request->session()->has('captcha_a')) {
            $this->regenerarCaptcha($request);
        }

        return [
            'a' => $request->session()->get('captcha_a'),
            'b' => $request->session()->get('captcha_b'),
        ];
    }

    protected function regenerarCaptcha(Request $request): void
    {
        $request->session()->put('captcha_a', random_int(1, 9));
        $request->session()->put('captcha_b', random_int(1, 9));
    }

    protected function validarCaptcha(Request $request): void
    {
        $esperado = (int) $request->session()->get('captcha_a') + (int) $request->session()->get('captcha_b');

        $valido = $request->filled('captcha') && (int) $request->input('captcha') === $esperado;

        $this->regenerarCaptcha($request);

        if (! $valido) {
            throw ValidationException::withMessages([
                'captcha' => 'La respuesta de verificación no es correcta, inténtalo nuevamente.',
            ]);
        }
    }
}
