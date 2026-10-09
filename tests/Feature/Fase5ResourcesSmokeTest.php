<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Models\Caso;
use App\Models\Cita;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Fase5ResourcesSmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_llamadas_list_with_tabs_and_create_render(): void
    {
        $user = $this->admin();
        $response = $this->actingAs($user)->get('/admin/llamadas');
        $response->assertOk();
        $response->assertSee('Hoy');
        $response->assertSee('Pendientes');
        $response->assertSee('Futuras');

        $this->actingAs($user)->get('/admin/llamadas/create')->assertOk();
    }

    public function test_citas_list_and_edit_render_with_informe_relation_manager(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->get('/admin/agenda')->assertOk();

        $record = Cita::first();
        $response = $this->actingAs($user)->get("/admin/agenda/{$record->id}/edit");
        $response->assertOk();
        // El relation manager carga en modo lazy (x-intersect), así que su título
        // no está en el HTML inicial; verificamos que el componente esté registrado.
        $response->assertSee('InformeRelationManager', escape: false);
    }

    public function test_abogado_only_sees_own_assigned_casos(): void
    {
        $abogado = User::whereHas('personal', fn ($q) => $q->where('rol', Rol::Abogado->value))->first();

        if (! $abogado) {
            $this->markTestSkipped('No hay usuario Abogado en los datos migrados.');
        }

        $totalCasos = Caso::count();
        $misCasos = Caso::whereHas('personal', fn ($q) => $q->where('personal.id', $abogado->personal_id))->count();

        // Solo tiene sentido validar el recorte si el abogado tiene MENOS casos que el total.
        if ($misCasos >= $totalCasos) {
            $this->markTestSkipped('Este abogado está asignado a todos los casos; el recorte no es observable.');
        }

        $response = $this->actingAs($abogado)->get('/admin/casos');
        $response->assertOk();

        $visibles = CasoResource::getEloquentQuery()->count();
        $this->assertSame($misCasos, $visibles);
        $this->assertLessThan($totalCasos, $visibles);
    }
}
