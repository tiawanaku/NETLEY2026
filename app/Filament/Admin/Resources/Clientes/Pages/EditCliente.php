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
        // Desde "Casos cerrados": Cliente Ejecutivo › Casos cerrados › ID cliente.
        if ($this->soloCerrados) {
            return [
                ['Cliente Ejecutivo'],
                ['Casos cerrados', ClienteResource::getUrl('cerrados')],
                [\App\Filament\Admin\Support\Migas::idCliente($this->getRecord())],
            ];
        }

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
                        'ci', 'extension', 'fecha_nacimiento', 'genero',
                    ]),
                    ...$cliente->only(CamposDomicilio::COLUMNAS),
                    'fecha_inicio' => now()->toDateString(),
                    'fecha_primera_cuota' => now()->addMonth()->toDateString(),
                    'patrocinio_hih' => false,
                    'servicio_psicologia' => false,
                    'servicio_trabajo_social' => false,
                    'servicio_otro' => false,
                    // Costas y costos: el paso 3 llega con los montos del caso
                    // de origen (iguala profesional = iguala Netley + patrocinio).
                    ...(($origen = $this->casoOrigenCostas($arguments))
                        ? [
                            // Paso 2: materia legal y apersonamiento del caso de
                            // origen; tipo de proceso según la materia.
                            ...self::datosProcesoCostas($origen),
                            // Paso 3: Costos = total del proceso anterior; Costas e
                            // Iguala profesional se llenan; Total = la suma.
                            'monto_origen_costas' => self::montoOrigenCostas($origen),
                            'monto_costas' => null,
                            'monto_iguala_costas' => null,
                            'iguala' => self::montoOrigenCostas($origen),
                            'patrocinio_hih' => (bool) $origen->patrocinio_hih,
                            'monto_patrocinio_hih' => $origen->patrocinio_hih ? (int) round((float) $origen->monto_patrocinio) : null,
                            'comision_porcentaje' => $origen->comision_porcentaje,
                            'comision_monto' => $origen->comision_monto !== null ? (int) round((float) $origen->comision_monto) : null,
                        ]
                        : []),
                    ...($origen
                        ? ['descripcion' => 'Costas y costos del caso N° '.$origen->id.($origen->materia_legal ? ' ('.$origen->materia_legal.')' : '')
                            // Saldo y responsable indicados al cerrar el caso ganado.
                            .($origen->informeCierre ? '. Saldo al cierre: Bs. '.number_format((float) $origen->informeCierre->saldo, 2, '.', '')
                                .(filled($origen->informeCierre->asume) ? ' — responsable: '.$origen->informeCierre->asume : '') : '')]
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

                // Costas y costos: Total = Costos (proceso anterior) + Costas +
                // Iguala profesional (se vuelve a calcular acá, no se confía en lo enviado).
                if ($origen) {
                    $costos = self::montoOrigenCostas($origen);
                    $data['iguala'] = ClienteCasoWizard::totalCostas($costos, $data['monto_costas'] ?? 0, $data['monto_iguala_costas'] ?? 0);
                    // Desglose del total en la nota del proceso.
                    $data['descripcion'] = trim(($data['descripcion'] ?? '')."\n"
                        .'Costos Bs. '.$costos.' + Costas Bs. '.max((int) ($data['monto_costas'] ?? 0), 0)
                        .' + Iguala profesional Bs. '.max((int) ($data['monto_iguala_costas'] ?? 0), 0)
                        .' = Total Bs. '.$data['iguala']);
                    // El patrocinio HiH también se cobra (ver ClienteCasoWizard::plan()).
                    $data['es_costas'] = true;
                }

                $nuevo = ClienteCasoWizard::crearParaCliente($cliente, $data)['caso'];

                if ($origen) {
                    $nuevo->update(['caso_origen_id' => $origen->id]);
                }

                Notification::make()->title('Caso creado')->success()->send();

                // Recarga la ficha en la pestaña "Casos" para ver el nuevo.
                $this->redirect(ClienteResource::getUrl('edit', ['record' => $cliente]).'?relation=0');
            });
    }

    /**
     * Paso 2 del proceso de costas y costos: misma materia legal y
     * apersonamiento que el caso de origen, y el tipo de proceso que
     * corresponde (del catálogo de esa materia; con materia "Otros", escrito).
     *
     * @return array<string, mixed>
     */
    protected static function datosProcesoCostas(\App\Models\Caso $origen): array
    {
        $area = $origen->especialidad?->value;
        $tipo = ClienteCasoWizard::tipoCostas($area);
        $delitoId = $area ? \App\Models\Delito::query()->where('area', $area)->where('delito', $tipo)->value('id') : null;

        $apersonamiento = $origen->apersonamiento;
        $enLista = blank($apersonamiento) || in_array($apersonamiento, ClienteCasoWizard::APERSONAMIENTOS, true);

        return [
            // Materia del catálogo o, si el origen era "Otros", la escrita a mano.
            'especialidad' => $area ?? \App\Filament\Admin\Forms\Components\DelitoSelect::OTROS,
            'materia_texto' => $area ? null : $origen->materia_texto,
            'delito_id' => $delitoId ? (string) $delitoId : \App\Filament\Admin\Forms\Components\DelitoSelect::OTROS,
            'delito_texto' => $delitoId ? null : $tipo,
            'apersonamiento' => $enLista ? $apersonamiento : ClienteCasoWizard::APERSONAMIENTO_OTROS,
            'apersonamiento_otro' => $enLista ? null : $apersonamiento,
        ];
    }

    /** Monto del proceso original: iguala profesional (iguala Netley + patrocinio HiH). */
    protected static function montoOrigenCostas(\App\Models\Caso $origen): int
    {
        return (int) round($origen->igualaProfesional());
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

    /** Desde "Casos cerrados" (?cerrados=1): la lista de procesos muestra solo los cerrados. */
    public bool $soloCerrados = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->editando = request()->boolean('editar') && auth()->user()?->puede('editar');
        $this->soloCerrados = request()->boolean('cerrados');
    }

    /** La lista de procesos recibe si debe mostrar solo los cerrados. */
    public function getRelationManagers(): array
    {
        return array_map(
            fn ($manager) => $manager === \App\Filament\Admin\Resources\Clientes\RelationManagers\CasosRelationManager::class
                ? $manager::make(['soloCerrados' => $this->soloCerrados])
                : $manager,
            parent::getRelationManagers(),
        );
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
