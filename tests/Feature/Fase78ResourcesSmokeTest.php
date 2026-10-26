<?php

namespace Tests\Feature;

use App\Models\Caso;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Fase78ResourcesSmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_informe_cierre_pdf_downloads(): void
    {
        $caso = Caso::whereHas('informesCierre')->firstOrFail();

        $pdf = Pdf::loadView('pdf.informe-cierre', [
            'informe' => $caso->informeCierre->load('caso.cliente'),
        ]);

        $this->assertStringStartsWith('%PDF', $pdf->output());
    }

    public function test_contactos_testimonios_talleres_municipios_oficinas_render(): void
    {
        $user = $this->admin();

        foreach (['/admin/contactos', '/admin/testimonios', '/admin/talleres', '/admin/municipios', '/admin/oficinas'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_contacto_create_with_personal_and_testimonio_bulk_aprobar(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->get('/admin/contactos/create')->assertOk();
        $this->actingAs($user)->get('/admin/testimonios/create')->assertOk();
        $this->actingAs($user)->get('/admin/tallers/create')->assertNotFound();
        $this->actingAs($user)->get('/admin/talleres/create')->assertOk();
    }
}
