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
    protected static string $resource = ClienteResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make(ClienteCasoWizard::steps())
                ->nextAction(fn ($action) => $action->extraAttributes(['data-enter-submit' => 'true']))
                ->submitAction($this->getCreateFormAction())
                ->columnSpanFull(),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return ClienteCasoWizard::crear($data)['cliente'];
    }

    // El botón de crear ya lo pone el wizard en su último paso (submitAction);
    // aquí solo se deja "Cancelar" para no duplicar el botón debajo.
    protected function getFormActions(): array
    {
        return [
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        /** @var Cliente $record */
        $record = $this->getRecord();

        return ClienteResource::getUrl('edit', ['record' => $record]);
    }
}
