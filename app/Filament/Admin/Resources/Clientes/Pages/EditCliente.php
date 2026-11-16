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
    // Ruta de navegación por secciones, sin el título repetido (ver Support\Migas).
    use \App\Filament\Admin\Concerns\MigasDeNavegacion;

    protected function migas(): array
    {
        return \App\Filament\Admin\Support\Migas::clientes($this->getRecord());
    }

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
            // "Iniciar costas y costos" (listado de casos del cliente) llega
            // con el caso ganado de origen: ?actionArguments[origen]=ID.
            ->modalHeading(fn (array $arguments): string => ($origen = $this->casoOrigenCostas($arguments))
                ? 'Costas y costos — caso N° '.$origen->id
                : 'Nuevo proceso')
            ->modalWidth('5xl')
            ->fillForm(function (array $arguments): array {
                /** @var Cliente $cliente */
                $cliente = $this->getRecord();

                return [
                    'id_cliente' => (string) ($cliente->nro_cliente ?? $cliente->getKey()),
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
                    ...(($origen = $this->casoOrigenCostas($arguments))
                        ? ['descripcion' => 'Costas y costos del caso N° '.$origen->id.($origen->materia_legal ? ' ('.$origen->materia_legal.')' : '')]
                        : []),
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
            ->action(function (array $data, array $arguments, Action $action): void {
                /** @var Cliente $cliente */
                $cliente = $this->getRecord();

                // La CI es única: si se cambió por la de otro cliente, no se guarda.
                if (Cliente::withTrashed()->where('ci', $data['ci'])->whereKeyNot($cliente->getKey())->exists()) {
                    Notification::make()->title('Esa CI ya pertenece a otro cliente')->body('Revisa el carnet de identidad en el paso 1.')->danger()->send();
                    $action->halt();
                }

                $origen = $this->casoOrigenCostas($arguments);

                $nuevo = ClienteCasoWizard::crearParaCliente($cliente, $data)['caso'];

                if ($origen) {
                    $nuevo->update(['caso_origen_id' => $origen->id]);
                }

                Notification::make()->title('Caso creado')->success()->send();

                // Recarga la ficha en la pestaña "Casos" para ver el nuevo.
                $this->redirect(ClienteResource::getUrl('edit', ['record' => $cliente]).'?relation=0');
            });
    }

    /** Caso ganado de origen de "Iniciar costas y costos" (si sigue pendiente). */
    protected function casoOrigenCostas(array $arguments): ?\App\Models\Caso
    {
        $id = $arguments['origen'] ?? null;

        if (blank($id)) {
            return null;
        }

        $caso = $this->getRecord()->casos()->find($id);

        return $caso?->requiereCostasYCostos() ? $caso : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Habilita los datos personales (pestaña "Datos del cliente").
            Action::make('editarCliente')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => ! $this->editando && auth()->user()?->puede('editar'))
                ->action(fn () => $this->redirect($this->urlFicha(editar: true))),
            // PDF con los datos personales y el mapa del domicilio.
            Action::make('imprimirCliente')
                ->label('Imprimir datos')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn () => ! $this->editando)
                ->action(fn () => response()->streamDownload(
                    fn () => print (\Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.ficha-cliente', ['cliente' => $this->getRecord()])->output()),
                    'datos-cliente-'.($this->getRecord()->nro_cliente ?? $this->getRecord()->getKey()).'.pdf'
                )),
            ClienteActions::gestionarAcceso(),
            // Sin "Eliminar" en la ficha (a pedido): el cliente se elimina
            // desde el listado de Clientes ejecutivos.
        ];
    }

    /**
     * La ficha abre de solo lectura; con "Editar" (?editar=1) se habilitan
     * los datos personales (ver ClienteForm) y aparecen Guardar / Cancelar.
     * Mismo patrón que Personal, Consultas y Casos.
     */
    public bool $editando = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->editando = request()->boolean('editar') && auth()->user()?->puede('editar');
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        abort_unless(auth()->user()?->puede('editar'), 403);

        // La CI es única: no se puede poner la de otro cliente.
        $ci = trim((string) ($this->data['ci'] ?? ''));

        if ($ci !== '' && Cliente::withTrashed()->where('ci', $ci)->whereKeyNot($this->getRecord()->getKey())->exists()) {
            Notification::make()->title('Esa CI ya pertenece a otro cliente')->body('Revisa el carnet de identidad.')->danger()->send();

            return;
        }

        parent::save(false, $shouldSendSavedNotification);

        $this->editando = false;

        $this->redirect($this->urlFicha());
    }

    /** Guardar / Cancelar solo en edición (recargan la ficha). */
    protected function getFormActions(): array
    {
        if (! $this->editando) {
            return [];
        }

        return [
            $this->getSaveFormAction()->label('Guardar'),
            Action::make('cancelarEdicion')
                ->label('Cancelar')
                ->color('gray')
                ->action(fn () => $this->redirect($this->urlFicha())),
        ];
    }

    /** Ficha de este cliente; con $editar abre directo en modo edición. */
    protected function urlFicha(bool $editar = false): string
    {
        $url = ClienteResource::getUrl('edit', ['record' => $this->getRecord()]);

        return $editar ? $url.'?editar=1' : $url;
    }
}
