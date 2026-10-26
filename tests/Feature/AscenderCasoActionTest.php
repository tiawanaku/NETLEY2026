<?php

namespace Tests\Feature;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Consultas\Pages\ListConsultas;
use App\Models\Caso;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Delito;
use App\Models\Pago;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class AscenderCasoActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ascender_consulta_crea_cliente_caso_pago_y_plan_de_cuotas(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        // Debe estar en la pestaña "Abiertas" (por defecto) del listado para que la tabla la resuelva.
        $consulta = Consulta::whereNull('caso_id')->firstOrFail();
        $consulta->update(['estado' => EstadoConsulta::Pendiente]);
        $cliente = Cliente::firstOrFail();
        $delito = Delito::where('area', 'CIVIL')->firstOrFail();
        $abogado = Personal::firstOrFail();

        $pagosPrevios = Pago::count();

        Livewire::actingAs($admin)
            ->test(ListConsultas::class)
            ->callTableAction('ascenderCaso', $consulta, data: [
                'cliente_id' => $cliente->id,
                'especialidad' => 'CIVIL',
                'delito_id' => $delito->id,
                'descripcion' => 'Caso de prueba generado por test automatizado',
                'apersonamiento' => 'Demandante',
                'personal' => [$abogado->id],
                'fecha_inicio' => now()->toDateString(),
                'iguala' => 3000,
                'anticipo' => 600,
                'numero_cuotas' => 4,
                'fecha_primera_cuota' => now()->addMonth()->toDateString(),
            ])
            ->assertHasNoTableActionErrors();

        $consulta->refresh();

        $this->assertNotNull($consulta->caso_id);
        $this->assertSame(EstadoConsulta::CasoIniciado, $consulta->estado);

        $caso = Caso::findOrFail($consulta->caso_id);
        $this->assertSame($cliente->id, $caso->cliente_id);
        $this->assertEquals(3000, $caso->iguala);
        $this->assertEquals(2400, $caso->saldo); // 3000 - 600 anticipo
        $this->assertTrue($caso->personal->contains($abogado));

        $this->assertSame($pagosPrevios + 1, Pago::count());
        $this->assertSame(4, $caso->planesPago()->count());
        $this->assertEquals(600, $caso->planesPago()->first()->monto); // 2400 / 4 cuotas
        $this->assertEquals(2400, $caso->planesPago()->sum('monto'));
    }
}
