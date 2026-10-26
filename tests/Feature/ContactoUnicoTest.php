<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Enums\EstadoPersonal;
use App\Enums\Rol;
use App\Filament\Admin\Resources\Clientes\Pages\CreateCliente;
use App\Filament\Admin\Resources\Consultas\Pages\CreateConsulta;
use App\Filament\Admin\Resources\Consultas\Pages\EditConsulta;
use App\Filament\Admin\Resources\Consultas\Pages\ListConsultas;
use App\Filament\Admin\Resources\Llamadas\Pages\ListLlamadas;
use App\Filament\Admin\Resources\Personals\Pages\CreatePersonal;
use App\Filament\Admin\Resources\Personals\Pages\EditPersonal;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Llamada;
use App\Models\Personal;
use App\Models\User;
use App\Rules\ContactoUnico;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Teléfono, WhatsApp y correo no se pueden repetir con OTRO registro de la
 * misma entidad (personal, clientes, consultas). Ver App\Rules\ContactoUnico.
 */
class ContactoUnicoTest extends TestCase
{
    use DatabaseTransactions;

    // ---------------------------------------------------------------
    // Regla (normalización)
    // ---------------------------------------------------------------

    public function test_variantes_de_telefono_igualan_formato_nuevo_legado_y_compacto(): void
    {
        $this->assertSame(['59171234567', '0059171234567', '71234567'], ContactoUnico::variantesTelefono('+591', '71234567'));
        $this->assertSame(['59171234567', '0059171234567', '71234567'], ContactoUnico::variantesTelefono(null, '+591 71234567'));
        $this->assertSame(['59171234567', '0059171234567', '71234567'], ContactoUnico::variantesTelefono(null, '71234567'));
        $this->assertSame(['59171234567', '0059171234567', '71234567'], ContactoUnico::variantesTelefono(null, '+59171234567'));
        $this->assertSame(['5171234567', '005171234567'], ContactoUnico::variantesTelefono('+51', '71234567'));
        $this->assertSame([], ContactoUnico::variantesTelefono('+591', ''));
    }

    // ---------------------------------------------------------------
    // Personal
    // ---------------------------------------------------------------

    public function test_personal_no_repite_telefono_de_otro_personal(): void
    {
        $numero = $this->numero();
        $this->personal(['nombres' => 'Juan', 'ap_paterno' => 'Pérez', 'telefono' => '+591 '.$numero]);

        $form = Livewire::actingAs($this->admin())
            ->test(CreatePersonal::class)
            ->fillForm($this->datosPersonal(['telefono_codigo' => '+591', 'telefono' => $numero]))
            ->call('create')
            ->assertHasFormErrors(['telefono']);

        $this->assertSame('Este teléfono ya está registrado para otro personal (Juan Pérez).', $this->error($form, 'telefono'));
    }

    public function test_personal_no_repite_whatsapp_cruzado_con_telefono_de_otro(): void
    {
        $numero = $this->numero();
        $this->personal(['telefono' => '+591 '.$numero]);

        $form = Livewire::actingAs($this->admin())
            ->test(CreatePersonal::class)
            ->fillForm($this->datosPersonal(['whatsapp_codigo' => '+591', 'whatsapp' => $numero]))
            ->call('create')
            ->assertHasFormErrors(['whatsapp']);

        $this->assertStringStartsWith('Este número de WhatsApp ya está registrado para otro personal', $this->error($form, 'whatsapp'));
    }

    public function test_personal_no_repite_correo_sin_distinguir_mayusculas(): void
    {
        $this->personal(['correo' => 'Repetido.'.$this->sufijo().'@Example.com']);
        $correo = Personal::latest('id')->value('correo');

        Livewire::actingAs($this->admin())
            ->test(CreatePersonal::class)
            ->fillForm($this->datosPersonal(['correo' => mb_strtoupper($correo)]))
            ->call('create')
            ->assertHasFormErrors(['correo']);
    }

    public function test_personal_formato_legado_y_compacto_cuentan_como_el_mismo_numero(): void
    {
        $legado = $this->numero();
        $compacto = $this->numero();
        $this->personal(['telefono' => $legado]); // dato antiguo: solo el número
        $this->personal(['telefono' => $this->numero(), 'whatsapp' => '+591'.$compacto]); // sin espacio

        Livewire::actingAs($this->admin())
            ->test(CreatePersonal::class)
            ->fillForm($this->datosPersonal(['telefono' => $legado, 'whatsapp' => $compacto]))
            ->call('create')
            ->assertHasFormErrors(['telefono', 'whatsapp']);
    }

    public function test_personal_mismo_numero_en_telefono_y_whatsapp_propios_esta_permitido(): void
    {
        $numero = $this->numero();
        $ci = (string) random_int(900000000, 999999999);

        Livewire::actingAs($this->admin())
            ->test(CreatePersonal::class)
            ->fillForm($this->datosPersonal(['ci' => $ci, 'telefono' => $numero, 'whatsapp' => $numero]))
            ->call('create')
            ->assertHasNoFormErrors();

        $personal = Personal::where('ci', $ci)->firstOrFail();
        $this->assertSame('+591 '.$numero, $personal->telefono);
        $this->assertSame('+591 '.$numero, $personal->whatsapp);
    }

    public function test_personal_editar_el_propio_registro_esta_permitido(): void
    {
        $numero = $this->numero();
        $personal = $this->personal(['telefono' => $numero, 'whatsapp' => '+591 '.$numero]);

        Livewire::actingAs($this->admin())
            ->test(EditPersonal::class, ['record' => $personal->getRouteKey()])
            ->fillForm(['nombres' => 'Editado', 'correo' => mb_strtoupper($personal->correo)])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Editado', $personal->refresh()->nombres);
    }

    public function test_personal_editar_a_un_numero_de_otro_se_bloquea(): void
    {
        $ajeno = $this->numero();
        $this->personal(['whatsapp' => '+591 '.$ajeno]);
        $personal = $this->personal();

        Livewire::actingAs($this->admin())
            ->test(EditPersonal::class, ['record' => $personal->getRouteKey()])
            ->fillForm(['telefono_codigo' => '+591', 'telefono' => $ajeno])
            ->call('save')
            ->assertHasFormErrors(['telefono']);
    }

    // ---------------------------------------------------------------
    // Cliente Ejecutivo (wizard)
    // ---------------------------------------------------------------

    public function test_wizard_cliente_bloquea_telefono_repetido_en_el_primer_paso(): void
    {
        $numero = $this->numero();
        $this->cliente(['nombres' => 'Ana', 'ap_paterno' => 'Rojas', 'telefono' => '+591 '.$numero]);

        $form = Livewire::actingAs($this->admin())
            ->test(CreateCliente::class)
            ->fillForm($this->datosCliente(['telefono' => $numero]))
            ->goToNextWizardStep()
            ->assertHasFormErrors(['telefono'])
            ->assertNotDispatched('next-wizard-step'); // no avanza al paso "Proceso"

        $this->assertSame('Este teléfono ya está registrado para otro cliente (Ana Rojas).', $this->error($form, 'telefono'));
    }

    public function test_wizard_cliente_bloquea_whatsapp_cruzado_y_correo_con_mayusculas(): void
    {
        $numero = $this->numero();
        $correo = 'cliente.'.$this->sufijo().'@example.com';
        $this->cliente(['telefono' => $numero, 'correo' => $correo]); // teléfono legado

        Livewire::actingAs($this->admin())
            ->test(CreateCliente::class)
            ->fillForm($this->datosCliente(['whatsapp' => $numero, 'correo' => mb_strtoupper($correo)]))
            ->goToNextWizardStep()
            ->assertHasFormErrors(['whatsapp', 'correo'])
            ->assertNotDispatched('next-wizard-step');
    }

    public function test_wizard_cliente_avanza_con_datos_nuevos_y_mismo_numero_en_telefono_y_whatsapp(): void
    {
        $numero = $this->numero();

        Livewire::actingAs($this->admin())
            ->test(CreateCliente::class)
            ->fillForm($this->datosCliente(['telefono' => $numero, 'whatsapp' => $numero]))
            ->goToNextWizardStep()
            ->assertHasNoFormErrors()
            ->assertDispatched('next-wizard-step');
    }

    public function test_wizard_cliente_con_la_misma_ci_no_cuenta_como_otro_cliente(): void
    {
        $numero = $this->numero();
        $existente = $this->cliente(['telefono' => '+591 '.$numero]);

        // crear() hace updateOrCreate por CI: es el mismo cliente.
        Livewire::actingAs($this->admin())
            ->test(CreateCliente::class)
            ->fillForm($this->datosCliente(['ci' => $existente->ci, 'telefono' => $numero]))
            ->goToNextWizardStep()
            ->assertHasNoFormErrors()
            ->assertDispatched('next-wizard-step');
    }

    public function test_cliente_ejecutivo_desde_consulta_bloquea_numero_de_otro_cliente(): void
    {
        $numero = $this->numero();
        $this->cliente(['whatsapp' => '+591 '.$numero]);
        $consulta = $this->consulta(['telefono' => $numero, 'estado' => EstadoConsulta::Pendiente]);

        Livewire::actingAs($this->admin())
            ->test(ListConsultas::class)
            ->callTableAction('ascenderCaso', $consulta, data: ['ci' => (string) random_int(900000000, 999999999)])
            ->assertHasTableActionErrors(['telefono']);

        $this->assertNull($consulta->refresh()->caso_id);
    }

    public function test_regla_ignora_el_propio_cliente_al_editar(): void
    {
        $numero = $this->numero();
        $cliente = $this->cliente(['telefono' => '+591 '.$numero]);

        $regla = fn (?Cliente $ignorar) => Validator::make(['telefono' => $numero], [
            'telefono' => [new ContactoUnico(Cliente::class, ContactoUnico::TELEFONO, codigo: '+591', ignorar: $ignorar, entidad: 'otro cliente')],
        ]);

        $this->assertTrue($regla(null)->fails());
        $this->assertFalse($regla($cliente)->fails());
    }

    // ---------------------------------------------------------------
    // Consultas
    // ---------------------------------------------------------------

    public function test_crear_consulta_bloquea_telefono_whatsapp_y_correo_repetidos(): void
    {
        $numero = $this->numero();
        $numeroWa = $this->numero();
        $correo = 'consulta.'.$this->sufijo().'@example.com';
        $this->consulta(['nombres' => 'Luis', 'ap_paterno' => 'Mamani', 'telefono' => $numero, 'whatsapp' => '+591 '.$numeroWa, 'correo' => $correo]);

        $form = Livewire::actingAs($this->admin())
            ->test(CreateConsulta::class)
            ->fillForm($this->datosConsulta([
                'telefono' => $numeroWa,      // cruzado: WhatsApp de la otra consulta
                'whatsapp' => $numero,        // cruzado: teléfono legado de la otra consulta
                'correo' => mb_strtoupper($correo),
            ]))
            ->call('create')
            ->assertHasFormErrors(['telefono', 'whatsapp', 'correo']);

        $this->assertSame('Este teléfono ya está registrado para otra consulta (Luis Mamani).', $this->error($form, 'telefono'));
        $this->assertSame('Este correo electrónico ya está registrado para otra consulta (Luis Mamani).', $this->error($form, 'correo'));
    }

    public function test_crear_consulta_con_datos_nuevos_y_campos_vacios_se_guarda(): void
    {
        $numero = $this->numero();
        $nombre = 'Prueba Unica '.str_shuffle('abcdefghijkl');

        Livewire::actingAs($this->admin())
            ->test(CreateConsulta::class)
            ->fillForm($this->datosConsulta(['nombres' => $nombre, 'telefono' => $numero, 'whatsapp' => $numero, 'correo' => null]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('+591 '.$numero, Consulta::where('nombres', $nombre)->value('whatsapp'));
    }

    public function test_editar_consulta_propia_sin_cambiar_contacto_esta_permitido_aunque_haya_duplicado_legado(): void
    {
        $numero = $this->numero();
        // Datos legados: dos consultas de la misma persona con el mismo número.
        $this->consulta(['telefono' => $numero]);
        $consulta = $this->consulta(['telefono' => $numero]);

        Livewire::actingAs($this->admin())
            ->test(EditConsulta::class, ['record' => $consulta->getRouteKey()])
            ->set('editando', true)
            ->fillForm(['consulta' => 'Texto editado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Texto editado', $consulta->refresh()->consulta);
    }

    public function test_editar_consulta_a_un_numero_de_otra_se_bloquea(): void
    {
        $ajeno = $this->numero();
        $this->consulta(['whatsapp' => '+591 '.$ajeno]);
        $consulta = $this->consulta(['telefono' => '+591 '.$this->numero()]);

        Livewire::actingAs($this->admin())
            ->test(EditConsulta::class, ['record' => $consulta->getRouteKey()])
            ->set('editando', true)
            ->fillForm(['telefono_codigo' => '+591', 'telefono' => $ajeno])
            ->call('save')
            ->assertHasFormErrors(['telefono']);
    }

    public function test_crear_consulta_desde_llamada_con_numero_registrado_no_crea_otra(): void
    {
        $numero = $this->numero();
        $this->consulta(['telefono' => '+591 '.$numero]);
        $llamada = Llamada::create(['nombres' => 'Prueba Llamada', 'telefono' => $numero, 'fecha' => now()]);
        $consultas = Consulta::count();

        Livewire::actingAs($this->admin())
            ->test(ListLlamadas::class)
            ->callTableAction('crearConsulta', $llamada, data: ['consulta' => 'Detalle'])
            ->assertNotified('No se puede crear la consulta');

        $this->assertSame($consultas, Consulta::count());
        $this->assertNull($llamada->refresh()->consulta_id);
    }

    public function test_portada_bloquea_consulta_con_telefono_o_correo_registrado_sin_revelar_nombre(): void
    {
        $numero = $this->numero();
        $correo = 'portada.'.$this->sufijo().'@example.com';
        $this->consulta(['nombres' => 'Nombre Secreto', 'whatsapp' => $numero, 'correo' => $correo]);
        $consultas = Consulta::count();

        $this->withSession(['captcha_a' => 2, 'captcha_b' => 3])
            ->post(route('frontpage.consulta.store'), [
                'nombres' => 'Visitante',
                'telefono_codigo' => '+591',
                'telefono' => $numero,
                'correo' => mb_strtoupper($correo),
                'consulta' => 'Consulta de prueba automatizada.',
                'captcha' => '5',
            ])
            ->assertSessionHasErrors([
                'telefono' => 'Ya existe una consulta registrada con este teléfono. Nos pondremos en contacto contigo.',
                'correo' => 'Ya existe una consulta registrada con este correo electrónico. Nos pondremos en contacto contigo.',
            ]);

        $this->assertSame($consultas, Consulta::count());
        $this->assertStringNotContainsString('Secreto', implode(' ', session('errors')->all()));
    }

    public function test_portada_acepta_el_mismo_numero_con_otro_codigo_de_pais(): void
    {
        $numero = $this->numero();
        $this->consulta(['telefono' => $numero]); // legado = +591

        $this->withSession(['captcha_a' => 2, 'captcha_b' => 3])
            ->post(route('frontpage.consulta.store'), [
                'nombres' => 'Visitante',
                'telefono_codigo' => '+51',
                'telefono' => $numero,
                'consulta' => 'Consulta de prueba automatizada.',
                'captcha' => '5',
            ])
            ->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function numero(): string
    {
        return (string) random_int(60000000, 79999999);
    }

    private function sufijo(): string
    {
        return bin2hex(random_bytes(4));
    }

    private function error(Testable $componente, string $campo): ?string
    {
        return $componente->errors()->first('data.'.$campo);
    }

    /** @param  array<string, mixed>  $datos */
    private function datosPersonal(array $datos = []): array
    {
        return [
            'nombres' => 'Prueba',
            'ap_paterno' => 'Contacto',
            'ci' => (string) random_int(900000000, 999999999),
            'fecha_nacimiento' => '1990-01-01',
            'telefono_codigo' => '+591',
            'telefono' => $this->numero(),
            'correo' => 'personal.'.$this->sufijo().'@example.com',
            'rol' => Rol::Secretaria->value,
            'estado' => EstadoPersonal::Habilitado->value,
            ...$datos,
        ];
    }

    /** @param  array<string, mixed>  $datos */
    private function personal(array $datos = []): Personal
    {
        $base = $this->datosPersonal();
        unset($base['telefono_codigo'], $base['rol']);

        return Personal::create([...$base, 'telefono' => '+591 '.$this->numero(), ...$datos]);
    }

    /** @param  array<string, mixed>  $datos */
    private function datosCliente(array $datos = []): array
    {
        return [
            'nombres' => 'Prueba',
            'ap_paterno' => 'Cliente',
            'ci' => (string) random_int(900000000, 999999999),
            'telefono_codigo' => '+591',
            'whatsapp_codigo' => '+591',
            ...$datos,
        ];
    }

    /** @param  array<string, mixed>  $datos */
    private function cliente(array $datos = []): Cliente
    {
        return Cliente::create([
            'nombres' => 'Prueba',
            'ap_paterno' => 'Existente',
            'ci' => (string) random_int(900000000, 999999999),
            ...$datos,
        ]);
    }

    /** @param  array<string, mixed>  $datos */
    private function datosConsulta(array $datos = []): array
    {
        return [
            'nombres' => 'Prueba',
            'telefono_codigo' => '+591',
            'whatsapp_codigo' => '+591',
            'consulta' => 'Consulta de prueba automatizada.',
            'fecha_consulta' => now()->toDateString(),
            'origen' => 'WhatsApp',
            ...$datos,
        ];
    }

    /** @param  array<string, mixed>  $datos */
    private function consulta(array $datos = []): Consulta
    {
        return Consulta::create([
            'nombres' => 'Prueba',
            'consulta' => 'Consulta existente.',
            'fecha_consulta' => now(),
            'origen' => 'Sitio web',
            'estado' => EstadoConsulta::Pendiente,
            ...$datos,
        ]);
    }
}
