<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Enums\EstadoPersonal;
use App\Enums\Rol;
use App\Filament\Admin\Resources\Consultas\Pages\CreateConsulta;
use App\Filament\Admin\Resources\Personals\Pages\CreatePersonal;
use App\Filament\Admin\Resources\Personals\Pages\EditPersonal;
use App\Filament\Admin\Resources\Personals\Pages\ListPersonals;
use App\Filament\Admin\Support\TelefonoColumna;
use App\Models\Consulta;
use App\Models\Personal;
use App\Models\User;
use App\Support\CodigosTelefonicos;
use App\Support\Telefono;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Teléfonos con selector de código de país (sin banderas): se guardan como
 * "+591 71234567" en la misma columna; los datos antiguos (solo el número)
 * se toman como bolivianos.
 */
class TelefonoCodigoPaisTest extends TestCase
{
    use DatabaseTransactions;

    public function test_catalogo_tiene_bolivia_primero_y_etiquetas_sin_banderas(): void
    {
        $opciones = CodigosTelefonicos::opciones();

        $this->assertSame('+591', array_key_first($opciones));
        $this->assertSame('+591 (Bolivia)', $opciones['+591']);
        $this->assertSame('+1 (EE. UU. / Canadá)', $opciones['+1']);
        $this->assertSame('+34 (España)', $opciones['+34']);

        foreach ($opciones as $etiqueta) {
            // Sin emojis de bandera (indicadores regionales U+1F1E6–U+1F1FF).
            $this->assertDoesNotMatchRegularExpression('/[\x{1F1E6}-\x{1F1FF}]/u', $etiqueta);
        }
    }

    public function test_busqueda_por_pais_alias_o_codigo_sin_tildes(): void
    {
        $this->assertSame(['+1'], array_keys(CodigosTelefonicos::buscar('estados unidos')));
        $this->assertSame(['+1'], array_keys(CodigosTelefonicos::buscar('USA')));
        $this->assertArrayHasKey('+591', CodigosTelefonicos::buscar('bolivia'));
        $this->assertArrayHasKey('+591', CodigosTelefonicos::buscar('591'));
        $this->assertArrayHasKey('+52', CodigosTelefonicos::buscar('mexico'));
        $this->assertCount(count(CodigosTelefonicos::PAISES), CodigosTelefonicos::buscar(''));
    }

    public function test_descomponer_componer_y_formatear(): void
    {
        $this->assertSame(['codigo' => '+591', 'numero' => '71234567'], Telefono::descomponer('+591 71234567'));
        $this->assertSame(['codigo' => '+591', 'numero' => '71234567'], Telefono::descomponer('71234567'));
        $this->assertSame(['codigo' => '+54', 'numero' => '91112345678'], Telefono::descomponer('+5491112345678'));
        $this->assertSame(['codigo' => '+1', 'numero' => '3055551234'], Telefono::descomponer('001 305 555 1234'));
        $this->assertSame(['codigo' => '+591', 'numero' => null], Telefono::descomponer(null));
        $this->assertSame(['codigo' => '+591', 'numero' => '2244 int. 105'], Telefono::descomponer('2244 int. 105'));

        $this->assertSame('+1 3055551234', Telefono::componer('+1', '3055551234'));
        $this->assertNull(Telefono::componer('+1', ''));

        $this->assertSame('+591 71234567', Telefono::formatear('71234567'));
        $this->assertSame('+54 91112345678', Telefono::formatear('+54 91112345678'));
        $this->assertNull(Telefono::formatear(''));

        $this->assertSame('https://wa.me/59171234567', Telefono::urlWhatsapp('+591 71234567'));
    }

    public function test_columna_de_tabla_muestra_codigo_y_numero_sin_bandera(): void
    {
        $html = (string) TelefonoColumna::html('71234567');

        $this->assertStringContainsString('+591 71234567', $html);
        $this->assertStringContainsString('https://wa.me/59171234567', $html);
        $this->assertStringNotContainsString('flagcdn', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_agregar_personal_guarda_codigo_de_pais_elegido(): void
    {
        $ci = (string) random_int(900000000, 999999999);

        Livewire::actingAs($this->admin())
            ->test(CreatePersonal::class)
            ->assertFormSet(['telefono_codigo' => '+591', 'whatsapp_codigo' => '+591'])
            ->fillForm([
                ...$this->datosPersonal($ci),
                'telefono_codigo' => '+1',
                'telefono' => '3055551234',
                'whatsapp' => '71234567',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $personal = Personal::where('ci', $ci)->firstOrFail();

        $this->assertSame('+1 3055551234', $personal->telefono);
        $this->assertSame('+591 71234567', $personal->whatsapp);
    }

    public function test_editar_personal_separa_codigo_y_numero_y_conserva_datos_antiguos(): void
    {
        $personal = Personal::create([
            ...$this->datosPersonal((string) random_int(900000000, 999999999)),
            'telefono' => '+54 91112345678',
            'whatsapp' => '71234567', // dato antiguo, sin código
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditPersonal::class, ['record' => $personal->getRouteKey()])
            ->assertFormSet([
                'telefono_codigo' => '+54',
                'telefono' => '91112345678',
                'whatsapp_codigo' => '+591',
                'whatsapp' => '71234567',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $personal->refresh();

        $this->assertSame('+54 91112345678', $personal->telefono);
        $this->assertSame('+591 71234567', $personal->whatsapp);
    }

    public function test_telefono_solo_acepta_digitos(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePersonal::class)
            ->fillForm([
                ...$this->datosPersonal((string) random_int(900000000, 999999999)),
                'telefono' => '7123abc',
            ])
            ->call('create')
            ->assertHasFormErrors(['telefono' => 'regex']);
    }

    public function test_telefono_de_personal_no_se_repite_aunque_el_existente_no_tenga_codigo(): void
    {
        $numero = (string) random_int(60000000, 79999999);

        Personal::create([
            ...$this->datosPersonal((string) random_int(900000000, 999999999)),
            'telefono' => $numero, // formato antiguo
        ]);

        Livewire::actingAs($this->admin())
            ->test(CreatePersonal::class)
            ->fillForm([
                ...$this->datosPersonal((string) random_int(900000000, 999999999)),
                'telefono_codigo' => '+591',
                'telefono' => $numero,
            ])
            ->call('create')
            ->assertHasFormErrors(['telefono']);

        // El mismo número con otro código de país sí es otro teléfono.
        Livewire::actingAs($this->admin())
            ->test(CreatePersonal::class)
            ->fillForm([
                ...$this->datosPersonal((string) random_int(900000000, 999999999)),
                'telefono_codigo' => '+51',
                'telefono' => $numero,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_lista_de_personal_no_muestra_la_columna_cargo(): void
    {
        $lista = Livewire::actingAs($this->admin())
            ->test(ListPersonals::class)
            ->assertTableColumnDoesNotExist('rol');

        $this->assertArrayNotHasKey('rol', $lista->instance()->getTable()->getFilters());
    }

    public function test_consulta_de_la_portada_guarda_codigo_y_queda_pendiente(): void
    {
        $nombre = 'Prueba Portada '.$this->sufijo();

        $this->withSession(['captcha_a' => 2, 'captcha_b' => 3])
            ->post(route('frontpage.consulta.store'), [
                'nombres' => $nombre,
                'telefono_codigo' => '+56',
                'telefono' => '912345678',
                'consulta' => 'Consulta de prueba automatizada.',
                'captcha' => '5',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $consulta = Consulta::where('nombres', $nombre)->firstOrFail();

        $this->assertSame('+56 912345678', $consulta->telefono);
        $this->assertSame(EstadoConsulta::Pendiente, $consulta->estado);
    }

    public function test_consulta_de_la_portada_rechaza_codigo_fuera_del_catalogo(): void
    {
        $this->withSession(['captcha_a' => 2, 'captcha_b' => 3])
            ->post(route('frontpage.consulta.store'), [
                'nombres' => 'Prueba',
                'telefono_codigo' => '+999',
                'telefono' => '71234567',
                'consulta' => 'x',
                'captcha' => '5',
            ])
            ->assertSessionHasErrors('telefono_codigo');
    }

    public function test_consulta_que_entra_por_whatsapp_queda_agendada(): void
    {
        $nombre = 'Prueba WhatsApp '.$this->sufijo();

        Livewire::actingAs($this->admin())
            ->test(CreateConsulta::class)
            ->fillForm([
                'nombres' => $nombre,
                'telefono' => '71234567',
                'consulta' => 'Consulta de prueba automatizada.',
                'fecha_consulta' => now()->toDateString(),
                'origen' => 'WhatsApp',
            ])
            ->assertFormSet(['estado' => EstadoConsulta::Agendado->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $consulta = Consulta::where('nombres', $nombre)->firstOrFail();

        $this->assertSame(EstadoConsulta::Agendado, $consulta->estado);
        $this->assertSame('+591 71234567', $consulta->telefono);
    }

    /** Sufijo solo con letras (el nombre de la consulta no admite números). */
    private function sufijo(): string
    {
        return collect(range(1, 8))->map(fn () => chr(random_int(97, 122)))->implode('');
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function datosPersonal(string $ci): array
    {
        return [
            'nombres' => 'Prueba',
            'ap_paterno' => 'Telefono',
            'ci' => $ci,
            'fecha_nacimiento' => '1990-01-01',
            'telefono' => (string) random_int(60000000, 79999999),
            // Único por persona: el correo no se puede repetir entre el personal.
            'correo' => 'prueba.telefono.'.bin2hex(random_bytes(4)).'@example.com',
            'rol' => Rol::Secretaria->value,
            'estado' => EstadoPersonal::Habilitado->value,
        ];
    }
}
