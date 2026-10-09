<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Caso;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Personal;
use App\Models\User;
use Tests\TestCase;

class Fase4ResourcesSmokeTest extends TestCase
{
    protected function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_personal_list_and_edit_render(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->get('/admin/personal')->assertOk();

        $record = Personal::first();
        $this->actingAs($user)->get("/admin/personal/{$record->id}/edit")->assertOk();
    }

    public function test_clientes_list_and_edit_render_with_relation_managers(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->get('/admin/clientes')->assertOk();

        $record = Cliente::first();
        $response = $this->actingAs($user)->get("/admin/clientes/{$record->id}/edit");
        $response->assertOk();
        $response->assertSee('Casos');
        $response->assertSee('Pagos');
        $response->assertSee('Documentos');
    }

    public function test_consultas_list_tabs_and_edit_render(): void
    {
        $user = $this->admin();
        $response = $this->actingAs($user)->get('/admin/consultas');
        $response->assertOk();
        $response->assertSee('Abiertas');
        $response->assertSee('Cerradas');

        $record = Consulta::first();
        $editResponse = $this->actingAs($user)->get("/admin/consultas/{$record->id}/edit");
        $editResponse->assertOk();
        $editResponse->assertSee('Respuestas');
        $editResponse->assertSee('Citas agendadas');
    }

    public function test_casos_list_and_edit_render_with_all_relation_managers(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->get('/admin/casos')->assertOk();

        $record = Caso::first();
        $response = $this->actingAs($user)->get("/admin/casos/{$record->id}/edit");
        $response->assertOk();
        $response->assertSee('Plan de pagos');
        $response->assertSee('Fiscalías');
        $response->assertSee('Juzgados');
        $response->assertSee('Otras instancias');
        $response->assertSee('Seguimiento del proceso');
    }

    public function test_caso_create_page_renders(): void
    {
        $this->actingAs($this->admin())->get('/admin/casos/create')->assertOk();
    }

    public function test_non_admin_cannot_access_personal_resource(): void
    {
        $abogado = User::whereHas('personal', fn ($q) => $q->where('rol', Rol::Abogado->value))->first();

        if (! $abogado) {
            $this->markTestSkipped('No hay un usuario con rol Abogado en los datos migrados.');
        }

        $this->actingAs($abogado)->get('/admin/personal')->assertForbidden();
    }
}
