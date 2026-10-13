<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Casos\Pages\CreateCaso;
use App\Filament\Admin\Resources\Casos\Pages\EditCaso;
use App\Filament\Admin\Resources\Consultas\Pages\EditConsulta;
use App\Models\Caso;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Delito;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Denunciado" (texto) y "Denuncias" (varios delitos del catálogo) en la
 * acción Agendar (consulta y caso), el wizard de Cliente Ejecutivo y las
 * fichas de Consulta y Caso.
 */
class DenunciadoDenunciasTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->firstOrFail();
    }

    public function test_agendar_en_consulta_guarda_denunciado_y_denuncias_y_los_precarga(): void
    {
        $consulta = $this->crearConsulta();
        [$d1, $d2] = $this->delitos(2);

        $pagina = Livewire::actingAs($this->admin)
            ->test(EditConsulta::class, ['record' => $consulta->getRouteKey()])
            ->callAction('agendarCita', data: [
                'fecha' => now()->addDay()->toDateString(),
                'hora' => '10:00',
                'detalle' => 'Cita de prueba',
                'denunciado' => 'Juan Pérez Denunciado',
                'denuncias' => [(string) $d1->id, (string) $d2->id],
            ])
            ->assertHasNoActionErrors()
            // La ficha abierta se actualiza sin recargar.
            ->assertSchemaStateSet([
                'denunciado' => 'Juan Pérez Denunciado',
                'denuncias' => [(string) $d1->id, (string) $d2->id],
            ]);

        $consulta->refresh();
        $this->assertSame('Juan Pérez Denunciado', $consulta->denunciado);
        $this->assertEqualsCanonicalizing([$d1->id, $d2->id], $consulta->delitosDenunciados->pluck('id')->all());
        $this->assertSame(1, $consulta->citas()->count());

        // Al volver a abrir Agendar, vienen precargados.
        $pagina->mountAction('agendarCita')
            ->assertActionDataSet([
                'denunciado' => 'Juan Pérez Denunciado',
                'denuncias' => [(string) $d1->id, (string) $d2->id],
            ]);
    }

    public function test_agendar_en_consulta_rechaza_delitos_fuera_del_catalogo(): void
    {
        $consulta = $this->crearConsulta();

        Livewire::actingAs($this->admin)
            ->test(EditConsulta::class, ['record' => $consulta->getRouteKey()])
            ->callAction('agendarCita', data: [
                'fecha' => now()->addDay()->toDateString(),
                'hora' => '10:00',
                'denuncias' => ['999999999'],
            ])
            ->assertHasActionErrors(['denuncias.0']);

        $this->assertSame(0, $consulta->delitosDenunciados()->count());
        $this->assertSame(0, $consulta->citas()->count());
    }

    public function test_ascender_consulta_traslada_denunciado_y_denuncias_al_caso(): void
    {
        $consulta = $this->crearConsulta();
        [$d1, $d2, $d3] = $this->delitos(3);
        $consulta->update(['denunciado' => 'Empresa XYZ S.R.L.']);
        $consulta->delitosDenunciados()->sync([$d1->id, $d2->id]);
        $abogado = $this->crearAbogado();

        $pagina = Livewire::actingAs($this->admin)
            ->test(EditConsulta::class, ['record' => $consulta->getRouteKey()])
            ->mountAction('ascenderCaso')
            // El paso "Proceso" viene precargado con lo de la consulta.
            ->assertActionDataSet([
                'denunciado' => 'Empresa XYZ S.R.L.',
                'denuncias' => [(string) $d1->id, (string) $d2->id],
            ]);

        // Se puede ajustar en el wizard antes de guardar.
        $pagina->setActionData([
            'nombres' => 'Ana',
            'ap_paterno' => 'Prueba',
            'ci' => '99887766',
            'especialidad' => 'PENAL',
            'delito_id' => (string) $d1->id,
            'apersonamiento' => 'Demandante',
            'personal' => [(string) $abogado->id],
            'fecha_inicio' => now()->toDateString(),
            'duracion_meses' => 3,
            'iguala' => 1500,
            'anticipo' => 0,
            'numero_cuotas' => 3,
            'fecha_primera_cuota' => now()->addMonth()->toDateString(),
            'denuncias' => [(string) $d1->id, (string) $d2->id, (string) $d3->id],
        ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $consulta->refresh();
        $caso = Caso::findOrFail($consulta->caso_id);

        $this->assertSame('Empresa XYZ S.R.L.', $caso->denunciado);
        $this->assertEqualsCanonicalizing([$d1->id, $d2->id, $d3->id], $caso->delitosDenunciados->pluck('id')->all());
        // El delito principal no cambia.
        $this->assertSame($d1->id, $caso->delito_id);
    }

    public function test_agendar_en_caso_guarda_y_la_ficha_los_muestra(): void
    {
        $caso = $this->crearCaso();
        [$d1, $d2] = $this->delitos(2);

        Livewire::actingAs($this->admin)
            ->test(EditCaso::class, ['record' => $caso->getRouteKey()])
            ->assertSchemaStateSet(['denunciado' => null, 'denuncias' => []])
            ->callAction('agendarCitaCaso', data: [
                'fecha' => now()->addDay()->toDateString(),
                'hora' => '09:30',
                'denunciado' => 'Pedro Gómez',
                'denuncias' => [(string) $d2->id],
            ])
            ->assertHasNoActionErrors()
            ->assertSchemaStateSet(['denunciado' => 'Pedro Gómez', 'denuncias' => [(string) $d2->id]]);

        $caso->refresh();
        $this->assertSame('Pedro Gómez', $caso->denunciado);
        $this->assertSame([$d2->id], $caso->delitosDenunciados->pluck('id')->all());
        $this->assertSame(1, $caso->citas()->count());

        // Al reabrir la ficha, se cargan desde la base de datos.
        Livewire::actingAs($this->admin)
            ->test(EditCaso::class, ['record' => $caso->getRouteKey()])
            ->assertSchemaStateSet(['denunciado' => 'Pedro Gómez', 'denuncias' => [(string) $d2->id]]);
    }

    public function test_crear_caso_desde_el_recurso_guarda_denunciado_y_denuncias(): void
    {
        $cliente = Cliente::forceCreate(['nombres' => 'Luis', 'ap_paterno' => 'Prueba', 'ci' => '55443322']);
        [$d1, $d2] = $this->delitos(2);

        Livewire::actingAs($this->admin)
            ->test(CreateCaso::class)
            ->fillForm([
                'cliente_id' => $cliente->id,
                'especialidad' => 'PENAL',
                'delito_id' => (string) $d1->id,
                'denunciado' => 'Carlos Ruiz',
                'denuncias' => [(string) $d1->id, (string) $d2->id],
                'fecha_inicio' => now()->toDateString(),
                'iguala' => 100,
                'saldo' => 100,
                'pagado' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $caso = Caso::where('cliente_id', $cliente->id)->firstOrFail();
        $this->assertSame('Carlos Ruiz', $caso->denunciado);
        $this->assertEqualsCanonicalizing([$d1->id, $d2->id], $caso->delitosDenunciados->pluck('id')->all());
    }

    protected function crearConsulta(): Consulta
    {
        return Consulta::create([
            'nombres' => 'Consulta',
            'ap_paterno' => 'Denuncias',
            'consulta' => 'Consulta de prueba automatizada',
            'fecha_consulta' => now(),
            'estado' => EstadoConsulta::Pendiente,
        ]);
    }

    protected function crearCaso(): Caso
    {
        $cliente = Cliente::forceCreate(['nombres' => 'Marta', 'ap_paterno' => 'Prueba', 'ci' => '11223344']);

        return Caso::create([
            'cliente_id' => $cliente->id,
            'especialidad' => 'PENAL',
            'fecha_inicio' => now()->toDateString(),
        ]);
    }

    protected function crearAbogado(): Personal
    {
        return Personal::forceCreate([
            'nombres' => 'Abogado',
            'ap_paterno' => 'Prueba',
            'ci' => '77665544',
            'profesion' => 'Abogado',
        ]);
    }

    /**
     * @return array<int, Delito>
     */
    protected function delitos(int $cantidad): array
    {
        return Delito::where('area', 'PENAL')->orderBy('id')->limit($cantidad)->get()->all();
    }
}
