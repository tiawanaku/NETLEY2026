<?php

namespace App\Filament\Admin\Resources\Clientes\Pages;

use App\Filament\Admin\Resources\Clientes\ClienteResource;
use App\Filament\Admin\Support\ClienteCasoWizard;
use App\Models\Cliente;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * "Crear cliente" usa el mismo flujo de 3 pasos que "Cliente Ejecutivo"
 * (ascender una consulta): datos del cliente, proceso y pago/plan de
 * cuotas, dando de alta el cliente junto con su primer caso.
 */
class CreateCliente extends CreateRecord
{
    // Ruta de navegación por secciones, sin el título repetido (ver Support\Migas).
    use \App\Filament\Admin\Concerns\MigasDeNavegacion;

    protected function migas(): array
    {
        return \App\Filament\Admin\Support\Migas::clientes(null, 'Nuevo cliente');
    }

    protected static string $resource = ClienteResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // "Imprimir recibo" del paso 3 guarda cliente + caso + anticipo.
            Wizard::make(ClienteCasoWizard::steps(fn (array $data) => ClienteCasoWizard::crear($data)['caso']))
                ->nextAction(fn ($action) => $action->extraAttributes(['data-enter-submit' => 'true']))
                ->submitAction($this->getCreateFormAction()->label('Guardar'))
                ->columnSpanFull(),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Ya guardado con "Imprimir recibo" (paso 3): solo los documentos.
        if ($caso = ClienteCasoWizard::casoYaGuardado($data)) {
            ClienteCasoWizard::adjuntarDocumentos($caso, $data['documentos'] ?? []);

            return $caso->cliente;
        }

        return ClienteCasoWizard::crear($data)['cliente'];
    }

    // El botón Guardar ya lo pone el wizard solo en su último paso
    // (submitAction); sin "Cancelar" (a pedido), igual que en "Cliente
    // ejecutivo" desde una consulta (AscenderCasoAction).
    protected function getFormActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        // Al listado de Clientes ejecutivos (igual que desde una consulta).
        return ClienteResource::getUrl('index');
    }
}
