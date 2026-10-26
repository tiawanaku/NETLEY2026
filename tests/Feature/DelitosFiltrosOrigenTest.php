<?php

namespace Tests\Feature;

use App\Enums\Especialidad;
use App\Enums\EstadoConsulta;
use App\Filament\Admin\Forms\Components\DelitoSelect;
use App\Filament\Admin\Resources\Casos\Pages\ListCasos;
use App\Filament\Admin\Resources\Clientes\Pages\CreateCliente;
use App\Filament\Admin\Resources\Clientes\Pages\ListClientes;
use App\Filament\Admin\Resources\Consultas\Pages\CreateConsulta;
use App\Filament\Admin\Resources\Consultas\Pages\EditConsulta;
use App\Filament\Admin\Resources\Consultas\Pages\ListConsultas;
use App\Filament\Admin\Resources\Llamadas\Pages\CreateLlamada;
use App\Filament\Admin\Resources\Llamadas\Pages\ListLlamadas;
use App\Filament\Admin\Resources\Personals\Pages\ListPersonals;
use App\Filament\Admin\Support\FiltrosContacto;
use App\Models\Caso;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Delito;
use App\Models\Llamada;
use App\Models\Personal;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * - Crear cliente: el campo "Delitos" reemplaza a "Materia legal" +
 *   "Delito / Materia específica"; la materia sale del área del delito.
 * - Filtros Teléfono / WhatsApp / Correo en las tablas.
 * - Origen "Talleres" con su detalle.
 */
class DelitosFiltrosOrigenTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->firstOrFail();
    }

    // ---- 1. Crear cliente: "Delitos" -------------------------------------

    public function test_crear_cliente_muestra_delitos_y_deriva_la_materia_del_area(): void
    {
        $penal = Delito::where('area', Especialidad::Penal->value)->firstOrFail();
        $abogado = $this->crearAbogado();
        $ci = (string) random_int(900000000, 999999999);

        Livewire::actingAs($this->admin)
            ->test(CreateCliente::class)
            ->assertFormFieldExists('delito_id', fn (Select $campo): bool => $campo->getLabel() === 'Delitos')
            // La materia ya no se elige aparte (solo con "Otro").
            ->assertFormFieldHidden('especialidad')
            ->fillForm([
                ...$this->datosWizard($ci, $abogado),
                'delito_id' => (string) $penal->id,
            ])
            // La materia (que filtra los abogados) se toma del área.
            ->assertFormSet(['especialidad' => Especialidad::Penal->value])
            ->assertFormFieldHidden('especialidad')
            // Aunque llegue otra materia, al guardar manda el área del delito.
            ->set('data.especialidad', Especialidad::Civil->value)
            ->call('create')
            ->assertHasNoFormErrors();

        $caso = Cliente::where('ci', $ci)->firstOrFail()->casos()->firstOrFail();

        $this->assertSame(Especialidad::Penal, $caso->especialidad);
        $this->assertSame($penal->id, $caso->delito_id);
        $this->assertNull($caso->materia_texto);
        $this->assertNull($caso->delito_texto);
    }

    public function test_delitos_otro_pide_materia_y_delito_a_mano(): void
    {
        $abogado = $this->crearAbogado();
        $ci = (string) random_int(900000000, 999999999);

        $pagina = Livewire::actingAs($this->admin)
            ->test(CreateCliente::class)
            ->fillForm([
                ...$this->datosWizard($ci, $abogado),
                'delito_id' => DelitoSelect::OTROS,
            ])
            ->assertFormFieldVisible('especialidad')
            ->assertFormFieldVisible('delito_texto')
            ->call('create')
            ->assertHasFormErrors(['especialidad' => 'required', 'delito_texto' => 'required']);

        $pagina->fillForm([
            'especialidad' => DelitoSelect::OTROS,
            'materia_texto' => 'Administrativo',
            'delito_texto' => 'Sumario administrativo',
        ])
            ->assertFormFieldVisible('materia_texto')
            ->call('create')
            ->assertHasNoFormErrors();

        $caso = Cliente::where('ci', $ci)->firstOrFail()->casos()->firstOrFail();

        $this->assertNull($caso->especialidad);
        $this->assertSame('Administrativo', $caso->materia_texto);
        $this->assertNull($caso->delito_id);
        $this->assertSame('Sumario administrativo', $caso->delito_texto);
    }

    // ---- 2. Filtros Teléfono / WhatsApp / Correo --------------------------

    public function test_busqueda_de_telefono_con_y_sin_codigo(): void
    {
        $this->assertSame(['digitos' => '71234567', 'codigo' => null, 'numero' => null], FiltrosContacto::busquedaTelefono('7123 4567'));
        $this->assertSame(['digitos' => '59171234567', 'codigo' => '+591', 'numero' => '71234567'], FiltrosContacto::busquedaTelefono('+591 71234567'));
        $this->assertSame(['digitos' => '59171234567', 'codigo' => '+591', 'numero' => '71234567'], FiltrosContacto::busquedaTelefono('00591-7123-4567'));
        $this->assertSame(['digitos' => '59171234567', 'codigo' => '+591', 'numero' => '71234567'], FiltrosContacto::busquedaTelefono('591 71234567'));
        $this->assertSame('+54', FiltrosContacto::busquedaTelefono('+54 9 11 3333 4444')['codigo']);
        $this->assertNull(FiltrosContacto::busquedaTelefono('  '));
    }

    public function test_filtros_de_contacto_en_clientes(): void
    {
        [$nuevo, $legado, $extranjero] = $this->numeros(3);

        $c1 = Cliente::forceCreate(['nombres' => 'Uno', 'ap_paterno' => 'Filtro', 'ci' => (string) random_int(900000000, 999999999), 'telefono' => "+591 {$nuevo}", 'correo' => 'uno.filtro@ejemplo-netley.test']);
        $c2 = Cliente::forceCreate(['nombres' => 'Dos', 'ap_paterno' => 'Filtro', 'ci' => (string) random_int(900000000, 999999999), 'telefono' => $legado, 'whatsapp' => "+591 {$legado}"]);
        $c3 = Cliente::forceCreate(['nombres' => 'Tres', 'ap_paterno' => 'Filtro', 'ci' => (string) random_int(900000000, 999999999), 'telefono' => "+54 {$extranjero}", 'correo' => 'TRES@otro-netley.test']);

        $lista = fn () => Livewire::actingAs($this->admin)->test(ListClientes::class);

        // Sin código, con código, con espacios y con 591 sin "+".
        foreach ([$nuevo, "+591 {$nuevo}", substr($nuevo, 0, 4).' '.substr($nuevo, 4), "591{$nuevo}"] as $busqueda) {
            $lista()->filterTable('telefono', ['valor' => $busqueda])
                ->assertCanSeeTableRecords([$c1])
                ->assertCanNotSeeTableRecords([$c2, $c3]);
        }

        // Dato legado (solo número) encontrado buscando con +591.
        $lista()->filterTable('telefono', ['valor' => "+591 {$legado}"])
            ->assertCanSeeTableRecords([$c2])
            ->assertCanNotSeeTableRecords([$c1, $c3]);

        // Otro país: +591 no coincide, +54 sí.
        $lista()->filterTable('telefono', ['valor' => "+591 {$extranjero}"])
            ->assertCanNotSeeTableRecords([$c1, $c2, $c3]);
        $lista()->filterTable('telefono', ['valor' => "+54 {$extranjero}"])
            ->assertCanSeeTableRecords([$c3])
            ->assertCanNotSeeTableRecords([$c1, $c2]);

        // Coincidencia parcial.
        $lista()->filterTable('whatsapp', ['valor' => substr($legado, 2, 5)])
            ->assertCanSeeTableRecords([$c2])
            ->assertCanNotSeeTableRecords([$c1, $c3]);

        $lista()->filterTable('correo', ['valor' => 'tres@OTRO'])
            ->assertCanSeeTableRecords([$c3])
            ->assertCanNotSeeTableRecords([$c1, $c2])
            // Indicador del filtro activo.
            ->assertSee('Correo: tres@OTRO');

        // Un "%" se busca literal, no como comodín.
        $lista()->filterTable('correo', ['valor' => '%'])
            ->assertCanNotSeeTableRecords([$c1, $c2, $c3]);
    }

    public function test_filtros_de_contacto_en_consultas_personal_y_casos(): void
    {
        [$tel, $wa] = $this->numeros(2);

        $consulta = $this->crearConsulta(['telefono' => "+591 {$tel}", 'whatsapp' => $wa, 'correo' => 'consulta.filtro@ejemplo-netley.test']);
        $otra = $this->crearConsulta(['telefono' => '+591 60000001']);

        Livewire::actingAs($this->admin)->test(ListConsultas::class)
            ->filterTable('telefono', ['valor' => $tel])
            ->assertCanSeeTableRecords([$consulta])
            ->assertCanNotSeeTableRecords([$otra])
            ->assertSee("Teléfono: {$tel}");

        Livewire::actingAs($this->admin)->test(ListConsultas::class)
            ->filterTable('whatsapp', ['valor' => "+591 {$wa}"])
            ->assertCanSeeTableRecords([$consulta])
            ->assertCanNotSeeTableRecords([$otra]);

        Livewire::actingAs($this->admin)->test(ListConsultas::class)
            ->filterTable('correo', ['valor' => 'consulta.filtro'])
            ->assertCanSeeTableRecords([$consulta])
            ->assertCanNotSeeTableRecords([$otra]);

        $personal = $this->crearAbogado(['telefono' => $tel, 'whatsapp' => "+591 {$wa}", 'correo' => 'abogado.filtro@ejemplo-netley.test']);
        $otroPersonal = $this->crearAbogado(['correo' => 'nadie@ejemplo-netley.test']);

        Livewire::actingAs($this->admin)->test(ListPersonals::class)
            ->filterTable('telefono', ['valor' => "+591 {$tel}"])
            ->assertCanSeeTableRecords([$personal])
            ->assertCanNotSeeTableRecords([$otroPersonal]);

        Livewire::actingAs($this->admin)->test(ListPersonals::class)
            ->filterTable('whatsapp', ['valor' => $wa])
            ->assertCanSeeTableRecords([$personal])
            ->assertCanNotSeeTableRecords([$otroPersonal]);

        Livewire::actingAs($this->admin)->test(ListPersonals::class)
            ->filterTable('correo', ['valor' => 'abogado.filtro'])
            ->assertCanSeeTableRecords([$personal])
            ->assertCanNotSeeTableRecords([$otroPersonal]);

        // Casos: por los datos de su cliente.
        $cliente = Cliente::forceCreate(['nombres' => 'Caso', 'ap_paterno' => 'Filtro', 'ci' => (string) random_int(900000000, 999999999), 'telefono' => "+591 {$tel}"]);
        $caso = Caso::create(['cliente_id' => $cliente->id, 'especialidad' => 'PENAL', 'fecha_inicio' => now()->toDateString()]);
        $otroCliente = Cliente::forceCreate(['nombres' => 'Otro', 'ap_paterno' => 'Filtro', 'ci' => (string) random_int(900000000, 999999999)]);
        $otroCaso = Caso::create(['cliente_id' => $otroCliente->id, 'especialidad' => 'PENAL', 'fecha_inicio' => now()->toDateString()]);

        Livewire::actingAs($this->admin)->test(ListCasos::class)
            ->filterTable('telefono', ['valor' => $tel])
            ->assertCanSeeTableRecords([$caso])
            ->assertCanNotSeeTableRecords([$otroCaso]);
    }

    public function test_tablas_solo_tienen_los_filtros_que_aplican(): void
    {
        $llamadas = Livewire::actingAs($this->admin)->test(ListLlamadas::class)
            ->assertTableFilterExists('telefono')
            ->assertTableFilterExists('whatsapp');

        // Llamadas no guarda correo.
        $this->assertNull($llamadas->instance()->getTable()->getFilter('correo'));
    }

    // ---- 3. Origen "Talleres" ---------------------------------------------

    public function test_origen_talleres_pide_y_guarda_el_detalle(): void
    {
        $nombre = 'Taller '.$this->sufijo();

        $pagina = Livewire::actingAs($this->admin)
            ->test(CreateConsulta::class)
            ->assertFormFieldHidden('origen_detalle')
            ->fillForm([
                'nombres' => $nombre,
                'consulta' => 'Consulta de prueba automatizada.',
                'fecha_consulta' => now()->toDateString(),
                'estado' => EstadoConsulta::Pendiente->value,
                'origen' => 'Talleres',
            ])
            ->assertFormFieldVisible('origen_detalle')
            ->call('create')
            ->assertHasFormErrors(['origen_detalle' => 'required']);

        $pagina->fillForm(['origen_detalle' => 'Colegio San Calixto'])
            ->call('create')
            ->assertHasNoFormErrors();

        $consulta = Consulta::where('nombres', $nombre)->firstOrFail();
        $this->assertSame('Talleres', $consulta->origen);
        $this->assertSame('Colegio San Calixto', $consulta->origen_detalle);
        $this->assertSame(EstadoConsulta::Pendiente, $consulta->estado);

        // Al reabrir la ficha se ve el detalle.
        Livewire::actingAs($this->admin)
            ->test(EditConsulta::class, ['record' => $consulta->getRouteKey()])
            ->assertFormFieldVisible('origen_detalle')
            ->assertFormSet(['origen' => 'Talleres', 'origen_detalle' => 'Colegio San Calixto']);

        // En la tabla, "Talleres — <detalle>".
        Livewire::actingAs($this->admin)->test(ListConsultas::class)
            ->assertTableColumnFormattedStateSet('origen', 'Talleres — Colegio San Calixto', $consulta);

        // Con otro origen el detalle se descarta.
        $consulta->update(['origen' => 'Facebook']);
        $this->assertNull($consulta->fresh()->origen_detalle);
    }

    public function test_origen_whatsapp_sigue_quedando_agendado_y_sin_detalle(): void
    {
        $nombre = 'Whatsapp '.$this->sufijo();

        Livewire::actingAs($this->admin)
            ->test(CreateConsulta::class)
            ->fillForm([
                'nombres' => $nombre,
                'consulta' => 'Consulta de prueba automatizada.',
                'fecha_consulta' => now()->toDateString(),
                'origen' => 'WhatsApp',
            ])
            ->assertFormFieldHidden('origen_detalle')
            ->assertFormSet(['estado' => EstadoConsulta::Agendado->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $consulta = Consulta::where('nombres', $nombre)->firstOrFail();
        $this->assertSame(EstadoConsulta::Agendado, $consulta->estado);
        $this->assertNull($consulta->origen_detalle);
    }

    public function test_llamada_con_origen_talleres_pasa_el_detalle_a_la_consulta(): void
    {
        $nombre = 'Llamada '.$this->sufijo();

        Livewire::actingAs($this->admin)
            ->test(CreateLlamada::class)
            ->fillForm(['nombres' => $nombre, 'origen' => 'Talleres'])
            ->assertFormFieldVisible('origen_detalle')
            ->fillForm(['origen_detalle' => 'Taller Universidad Mayor'])
            ->call('create')
            ->assertHasNoFormErrors();

        $llamada = Llamada::where('nombres', $nombre)->firstOrFail();
        $this->assertSame('Taller Universidad Mayor', $llamada->origen_detalle);

        Livewire::actingAs($this->admin)->test(ListLlamadas::class)
            ->set('activeTab', 'todas')
            ->callTableAction('crearConsulta', $llamada, data: ['consulta' => 'Desde la llamada'])
            ->assertHasNoTableActionErrors();

        $consulta = Consulta::findOrFail($llamada->fresh()->consulta_id);
        $this->assertSame('Talleres', $consulta->origen);
        $this->assertSame('Taller Universidad Mayor', $consulta->origen_detalle);
    }

    // ---- Ayudas -------------------------------------------------------------

    /** @return array<string, mixed> */
    private function datosWizard(string $ci, Personal $abogado): array
    {
        return [
            'nombres' => 'Prueba',
            'ap_paterno' => 'Delitos',
            'ci' => $ci,
            'apersonamiento' => 'Demandante',
            'personal' => [(string) $abogado->id],
            'fecha_inicio' => now()->toDateString(),
            'duracion_meses' => 2,
            'iguala' => 1000,
            'anticipo' => 0,
            'numero_cuotas' => 2,
            'fecha_primera_cuota' => now()->addMonth()->toDateString(),
        ];
    }

    /** @param array<string, mixed> $datos */
    private function crearAbogado(array $datos = []): Personal
    {
        return Personal::forceCreate([
            'nombres' => 'Abogado',
            'ap_paterno' => 'Filtro',
            'ci' => (string) random_int(900000000, 999999999),
            'profesion' => 'Abogado',
            ...$datos,
        ]);
    }

    /** @param array<string, mixed> $datos */
    private function crearConsulta(array $datos = []): Consulta
    {
        return Consulta::create([
            'nombres' => 'Consulta',
            'ap_paterno' => 'Filtro',
            'consulta' => 'Consulta de prueba automatizada',
            'fecha_consulta' => now(),
            'estado' => EstadoConsulta::Pendiente,
            ...$datos,
        ]);
    }

    /**
     * Números de 8 dígitos distintos entre sí y poco probables en otros datos.
     *
     * @return array<int, string>
     */
    private function numeros(int $cantidad): array
    {
        $numeros = [];

        while (count($numeros) < $cantidad) {
            $numeros[] = (string) random_int(60000000, 79999999);
            $numeros = array_values(array_unique($numeros));
        }

        return $numeros;
    }

    /** Sufijo solo con letras (los nombres no admiten números). */
    private function sufijo(): string
    {
        return collect(range(1, 8))->map(fn () => chr(random_int(97, 122)))->implode('');
    }
}
