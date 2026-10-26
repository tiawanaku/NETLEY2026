<?php

namespace Tests\Feature;

use App\Models\Caso;
use App\Models\Pago;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Fase6PagosTest extends TestCase
{
    use DatabaseTransactions;

    protected function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_pagos_list_and_create_render(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->get('/admin/pagos')->assertOk();
        $this->actingAs($user)->get('/admin/pagos/create')->assertOk();
    }

    public function test_pago_edit_page_does_not_exist(): void
    {
        $pago = Pago::firstOrFail();
        $this->actingAs($this->admin())->get("/admin/pagos/{$pago->id}/edit")->assertNotFound();
    }

    public function test_recibo_pdf_downloads(): void
    {
        $pago = Pago::with(['cliente', 'caso'])->firstOrFail();

        $response = $this->actingAs($this->admin())
            ->get('/admin/pagos');
        $response->assertOk();

        // Genera el PDF directamente igual que lo hace la Action, para verificar
        // que la plantilla Blade renderiza sin errores con datos reales.
        $pdf = Pdf::loadView('pdf.recibo-pago', ['pago' => $pago]);
        $content = $pdf->output();

        $this->assertStringStartsWith('%PDF', $content);
    }

    public function test_pago_observer_actualiza_saldo_del_caso(): void
    {
        $caso = Caso::where('saldo', '>', 100)->where('estado', '!=', 'cerrado')->firstOrFail();
        $saldoInicial = (float) $caso->saldo;
        $pagadoInicial = (float) $caso->pagado;

        $pago = Pago::create([
            'caso_id' => $caso->id,
            'cliente_id' => $caso->cliente_id,
            'monto' => 50,
            'fecha_pago' => now()->toDateString(),
            'nro_cuota' => 999,
            'nro_recibo' => ((int) Pago::max('nro_recibo')) + 1,
            'registrado_por' => 'test',
        ]);

        $caso->refresh();
        $this->assertEquals($saldoInicial - 50, (float) $caso->saldo);
        $this->assertEquals($pagadoInicial + 50, (float) $caso->pagado);

        $pago->delete();
        $caso->refresh();
        $this->assertEquals($saldoInicial, (float) $caso->saldo);
        $this->assertEquals($pagadoInicial, (float) $caso->pagado);
    }
}
