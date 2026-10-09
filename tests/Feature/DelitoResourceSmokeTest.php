<?php

namespace Tests\Feature;

use App\Models\Delito;
use App\Models\User;
use Tests\TestCase;

class DelitoResourceSmokeTest extends TestCase
{
    public function test_delitos_list_renders_for_an_authenticated_user(): void
    {
        $user = User::where('username', 'admin')->firstOrFail();

        $response = $this->actingAs($user)->get('/admin/delitos');

        $response->assertOk();
        $response->assertSee('Delitos');
        $response->assertSee('CIVIL'); // primera página, orden por defecto = área

        $this->assertSame(462, Delito::count());
    }

    public function test_delito_create_and_edit_pages_render(): void
    {
        $user = User::where('username', 'admin')->firstOrFail();
        $delito = Delito::first();

        $this->actingAs($user)->get('/admin/delitos/create')->assertOk();
        $this->actingAs($user)->get("/admin/delitos/{$delito->id}/edit")->assertOk();
    }

    public function test_login_page_uses_username_field(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('Usuario');
    }
}
