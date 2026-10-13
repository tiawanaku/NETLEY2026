<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAprobacion;
use App\Enums\EstadoCaso;
use App\Enums\EstadoConsulta;
use App\Models\Caso;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\ImagenCarrusel;
use App\Models\Oficina;
use App\Models\Personal;
use App\Models\RedSocial;
use App\Models\Testimonio;
use App\Models\VideoPortada;
use App\Rules\ContactoUnico;
use App\Support\CodigosTelefonicos;
use App\Support\Telefono;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        // Ya existe una consulta con este teléfono/WhatsApp o correo: no se
        // registra otra. Mensaje genérico, sin revelar a quién pertenece
        // (formulario público).
        $yaRegistrada = fn (string $dato): string => "Ya existe una consulta registrada con este {$dato}. Nos pondremos en contacto contigo.";

        $data = $request->validate([
            'nombres' => ['required', 'string', 'max:40'],
            'ap_paterno' => ['nullable', 'string', 'max:30'],
            // Código de país (selector "+591 (Bolivia)"…) y número aparte;
            // se guardan juntos como "+591 71234567" (ver Telefono::componer).
            'telefono_codigo' => ['required', Rule::in(CodigosTelefonicos::codigos())],
            'telefono' => ['bail', 'required', 'regex:/^[0-9]{6,14}$/', new ContactoUnico(
                Consulta::class,
                ContactoUnico::TELEFONO,
                codigo: (string) $request->input('telefono_codigo', CodigosTelefonicos::POR_DEFECTO),
                mensaje: $yaRegistrada('teléfono'),
            )],
            'correo' => ['bail', 'nullable', 'email', 'max:80', new ContactoUnico(
                Consulta::class,
                ContactoUnico::CORREO,
                mensaje: $yaRegistrada('correo electrónico'),
            )],
            'ciudad' => ['nullable', 'string', 'max:40'],
            'consulta' => ['required', 'string', 'max:3000'],
        ], [
            'telefono_codigo.*' => 'Selecciona el código de país del teléfono.',
            'telefono.required' => 'Ingresa tu número de teléfono / WhatsApp.',
            'telefono.regex' => 'El teléfono debe tener solo números (de 6 a 14 dígitos), sin el código de país.',
        ]);

        $data['telefono'] = Telefono::componer($data['telefono_codigo'], $data['telefono']);
        unset($data['telefono_codigo']);

        Consulta::create([
            ...$data,
            'fecha_consulta' => now(),
            'origen' => 'Sitio web',
            // Las consultas que llegan por la página web nacen "Pendiente".
            'estado' => EstadoConsulta::inicialSegunOrigen('Sitio web'),
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
