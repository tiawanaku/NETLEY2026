<?php

namespace App\Filament\Admin\Resources\Clientes\Pages;

use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Filament\Admin\Resources\Clientes\Actions\ClienteActions;
use App\Filament\Admin\Resources\Clientes\ClienteResource;
use App\Filament\Admin\Support\ClienteCasoWizard;
use App\Models\Cliente;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;

    /** El formulario va como primera pestaña, junto a "Casos". */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Datos personales';
    }

    public function getContentTabIcon(): string
    {
        return 'heroicon-o-user';
    }

    /** Render hook al final de la barra de pestañas (ver AdminPanelProvider). */
    public const HOOK_FIN_PESTANAS = 'netley::cliente.pestanas.fin';

    /**
     * Botón "Nuevo proceso" dentro de la misma barra de pestañas, justo
     * después de "Datos personales" / "Casos" (render hook al final del
     * <nav> de las pestañas, ver AdminPanelProvider y
     * filament/admin/clientes/boton-nuevo-proceso.blade.php). Reemplaza al
     * "Nuevo proceso" que estaba dentro de la tabla de Casos.
     */
    public function getRelationManagersContentComponent(): Component
    {
        $componente = parent::getRelationManagersContentComponent();

        if ($componente instanceof Tabs) {
            $componente->endRenderHooks([self::HOOK_FIN_PESTANAS]);
        }

        return $componente;
    }

    /**
     * Acción de página (método xxxAction(): Filament la resuelve por nombre
     * desde mountAction('anadirCaso') del botón de la barra de pestañas).
     * Mismos campos que los pasos "Proceso" y "Pago y plan de cuotas" del
     * wizard de Cliente Ejecutivo, en un solo formulario (el cliente ya
     * existe); el guardado replica la misma lógica financiera
     * (ClienteCasoWizard::crearCaso()).
     */
    protected function anadirCasoAction(): Action
    {
        return Action::make('anadirCaso')
            ->label('Nuevo proceso')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->visible(fn (): bool => CasoResource::canCreate())
            ->modalHeading('Nuevo proceso')
            ->modalWidth('5xl')
            ->schema(fn (Schema $schema): Schema => $schema
                ->inlineLabel()
                ->columns(2)
                ->components([
                    ...ClienteCasoWizard::camposProceso(),
                    ...ClienteCasoWizard::camposPago(),
                ]))
            ->modalSubmitActionLabel('Guardar')
            ->modalCancelActionLabel('Cancelar')
            ->action(function (array $data): void {
                /** @var Cliente $cliente */
                $cliente = $this->getRecord();

                ClienteCasoWizard::crearCaso($cliente, $data);

                Notification::make()->title('Caso creado')->success()->send();

                // Recarga la ficha en la pestaña "Casos" para ver el nuevo.
                $this->redirect(ClienteResource::getUrl('edit', ['record' => $cliente]).'?relation=0');
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            ClienteActions::gestionarAcceso(),
            DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
        ];
    }

    /**
     * La ficha del cliente queda de solo lectura (ver ClienteForm): todos
     * los campos están siempre deshabilitados en esta vista, así que no hay
     * nada que "Guardar" ni de qué "Cancelar".
     */
    protected function getFormActions(): array
    {
        return [];
    }
}
