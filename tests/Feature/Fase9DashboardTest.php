<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Fase9DashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_dashboard_renders_with_resumen_and_widgets(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin');
        $response->assertOk();
        $response->assertSee('Casos activos');
        $response->assertSee('Casos Vencidos');
        $response->assertSee('Casos Próximos a Vencer');
        $response->assertSee('Citas Programadas para Hoy');
        $response->assertSee('Llamadas Programadas para Hoy');
        $response->assertSee('Pagos por Vencer en 2 Días');
    }

    public function test_estadisticas_page_renders_for_admin(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/estadisticas');
        $response->assertOk();
    }

    public function test_estadisticas_forbidden_for_secretaria(): void
    {
        $secretaria = User::whereHas('personal', fn ($q) => $q->where('rol', Rol::Secretaria->value))->first();

        if (! $secretaria) {
            $this->markTestSkipped('No hay usuario Secretaria en los datos migrados.');
        }

        $this->actingAs($secretaria)->get('/admin/estadisticas')->assertForbidden();
    }

    public function test_dashboard_does_not_leak_finance_charts(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin');
        $response->assertOk();
        // Los widgets financieros/gráficos deben vivir solo en Estadísticas, no en el Dashboard
        // (ni siquiera registrados, ya que cargan en modo lazy y su texto nunca aparecería igual).
        $response->assertDontSee('IngresosMensualesWidget', escape: false);
        $response->assertDontSee('TopDelitosWidget', escape: false);
    }
}
