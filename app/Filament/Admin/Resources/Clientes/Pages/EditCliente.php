<?php

namespace App\Filament\Admin\Resources\Clientes\Pages;

use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Filament\Admin\Resources\Clientes\Actions\ClienteActions;
use App\Filament\Admin\Resources\Clientes\ClienteResource;
use App\Filament\Admin\Support\CamposDomicilio;
use Filament\Schemas\Components\Wizard;
use App\Filament\Admin\Support\ClienteCasoWizard;
use App\Models\Cliente;
use Filament\Actions\Action;
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
        return 'Datos del cliente';
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
     * Los mismos 4 pasos del formulario de Cliente ejecutivo: el paso 1 llega
     * con los datos personales de este cliente (se pueden corregir) y el
     * resto se llena para el caso nuevo (ClienteCasoWizard::crearParaCliente()).
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
            ->fillForm(function (): array {
                /** @var Cliente $cliente */
                $cliente = $this->getRecord();

                return [
                    ...$cliente->only([
                        'nombres', 'ap_paterno', 'ap_materno', 'telefono', 'whatsapp', 'correo',
                        'ci', 'extension', 'fecha_nacimiento',
                    ]),
                    ...$cliente->only(CamposDomicilio::COLUMNAS),
                    'fecha_inicio' => now()->toDateString(),
                    'fecha_primera_cuota' => now()->addMonth()->toDateString(),
                    'patrocinio_hih' => false,
                    'servicio_psicologia' => false,
                    'servicio_trabajo_social' => false,
                    'servicio_otro' => false,
                ];
            })
            // Mismo asistente que Cliente ejecutivo: Guardar solo en el paso
            // 4 y sin Cancelar (se cierra con la X), ver AscenderCasoAction.
            ->steps(ClienteCasoWizard::steps())
            ->modifyWizardUsing(fn (Wizard $wizard): Wizard => $wizard
                ->nextAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true'])))
            ->modalCancelAction(false)
            ->modalSubmitActionLabel('Guardar')
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true']))
            ->action(function (array $data, Action $action): void {
                /** @var Cliente $cliente */
                $cliente = $this->getRecord();

                // La CI es única: si se cambió por la de otro cliente, no se guarda.
                if (Cliente::withTrashed()->where('ci', $data['ci'])->whereKeyNot($cliente->getKey())->exists()) {
                    Notification::make()->title('Esa CI ya pertenece a otro cliente')->body('Revisa el carnet de identidad en el paso 1.')->danger()->send();
                    $action->halt();
                }

                ClienteCasoWizard::crearParaCliente($cliente, $data);

                Notification::make()->title('Caso creado')->success()->send();

                // Recarga la ficha en la pestaña "Casos" para ver el nuevo.
                $this->redirect(ClienteResource::getUrl('edit', ['record' => $cliente]).'?relation=0');
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            ClienteActions::gestionarAcceso(),
            // Sin "Eliminar" en la ficha (a pedido): el cliente se elimina
            // desde el listado de Clientes ejecutivos.
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
