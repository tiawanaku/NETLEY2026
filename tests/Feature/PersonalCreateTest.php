<?php

namespace Tests\Feature;

use App\Enums\EstadoPersonal;
use App\Enums\Rol;
use App\Filament\Admin\Resources\Personals\Pages\CreatePersonal;
use App\Filament\Admin\Resources\Personals\Pages\EditPersonal;
use App\Models\Personal;
use App\Models\User;
use App\Support\PersonalEspecialidades;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PersonalCreateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_agregar_personal_no_muestra_cargo_principal_ni_contrato_vigente(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(CreatePersonal::class)
            ->assertFormFieldDoesNotExist('profesion')
            ->assertFormFieldDoesNotExist('profesion_otro')
            ->assertFormFieldDoesNotExist('tiene_contrato');
    }

    public function test_se_puede_agregar_personal_sin_cargo_principal_ni_contrato_vigente(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $ci = (string) random_int(900000000, 999999999);
        $telefono = (string) random_int(60000000, 79999999);

        Livewire::actingAs($admin)
            ->test(CreatePersonal::class)
            ->fillForm([
                'nombres' => 'Prueba',
                'ap_paterno' => 'Automatizada',
                'ci' => $ci,
                'fecha_nacimiento' => '1990-01-01',
                'telefono' => $telefono,
                'correo' => 'prueba.personal@example.com',
                'rol' => Rol::Secretaria->value,
                'estado' => EstadoPersonal::Habilitado->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $personal = Personal::where('ci', $ci)->firstOrFail()->refresh();

        $this->assertNull($personal->profesion);
        $this->assertTrue($personal->tiene_contrato); // default de la columna
    }

    public function test_abogado_agregado_queda_con_cargo_principal_abogado(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $ci = (string) random_int(900000000, 999999999);

        Livewire::actingAs($admin)
            ->test(CreatePersonal::class)
            ->fillForm([
                'nombres' => 'Prueba',
                'ap_paterno' => 'Abogado',
                'ci' => $ci,
                'fecha_nacimiento' => '1990-01-01',
                'telefono' => (string) random_int(60000000, 79999999),
                'correo' => 'prueba.abogado@example.com',
                'rol' => Rol::Abogado->value,
                'estado' => EstadoPersonal::Habilitado->value,
                'cargo' => ['Abogado'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Abogado', Personal::where('ci', $ci)->firstOrFail()->profesion);
    }

    public function test_cargo_principal_se_deriva_de_las_profesiones(): void
    {
        $this->assertSame('Abogado', PersonalEspecialidades::profesionPrincipal(['Psicologia', 'Abogado']));
        $this->assertSame('Psicologo', PersonalEspecialidades::profesionPrincipal(['Psicologia']));
        $this->assertSame('Trabajador Social', PersonalEspecialidades::profesionPrincipal('Trabajo Social'));
        $this->assertNull(PersonalEspecialidades::profesionPrincipal(['Otros']));
        $this->assertNull(PersonalEspecialidades::profesionPrincipal([]));
    }

    public function test_editar_personal_no_muestra_cargo_principal_ni_contrato_vigente(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $personal = $this->crearPersonal(['cargo' => ['Secretaria'], 'profesion' => 'Secretaria']);

        Livewire::actingAs($admin)
            ->test(EditPersonal::class, ['record' => $personal->getRouteKey()])
            ->assertFormFieldExists('cargo')
            ->assertFormFieldDoesNotExist('profesion')
            ->assertFormFieldDoesNotExist('profesion_otro')
            ->assertFormFieldDoesNotExist('tiene_contrato');
    }

    public function test_editar_recalcula_cargo_principal_al_cambiar_profesion_y_no_toca_contrato(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $personal = $this->crearPersonal([
            'cargo' => ['Secretaria'],
            'profesion' => 'Secretaria',
            'tiene_contrato' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(EditPersonal::class, ['record' => $personal->getRouteKey()])
            ->fillForm(['cargo' => ['Abogado']])
            ->call('save')
            ->assertHasNoFormErrors();

        $personal->refresh();

        $this->assertSame(['Abogado'], $personal->cargo);
        $this->assertSame('Abogado', $personal->profesion);
        $this->assertFalse($personal->tiene_contrato);
    }

    public function test_editar_conserva_cargo_principal_legado_si_no_se_puede_derivar(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $personal = $this->crearPersonal(['cargo' => null, 'profesion' => 'Directora']);

        Livewire::actingAs($admin)
            ->test(EditPersonal::class, ['record' => $personal->getRouteKey()])
            ->fillForm(['nota' => 'Editado en prueba'])
            ->call('save')
            ->assertHasNoFormErrors();

        $personal->refresh();

        $this->assertSame('Editado en prueba', $personal->nota);
        $this->assertSame('Directora', $personal->profesion);
    }

    /** @param  array<string, mixed>  $atributos */
    private function crearPersonal(array $atributos = []): Personal
    {
        return Personal::create([
            'nombres' => 'Prueba',
            'ap_paterno' => 'Edicion',
            'ci' => (string) random_int(900000000, 999999999),
            'fecha_nacimiento' => '1990-01-01',
            'telefono' => (string) random_int(60000000, 79999999),
            'correo' => 'prueba.edicion@example.com',
            'rol' => Rol::Secretaria->value,
            'estado' => EstadoPersonal::Habilitado->value,
            ...$atributos,
        ]);
    }
}
