<?php

namespace App\Filament\Admin\Resources\Casos\Pages;

use App\Filament\Admin\Resources\Casos\Actions\CasoActions;
use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Filament\Admin\Support\DatosInstancia;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use App\Support\CronologiaCaso;
use Illuminate\Contracts\View\View;

class EditCaso extends EditRecord
{
    // Ruta de navegación por secciones, sin el título repetido (ver Support\Migas).
    use \App\Filament\Admin\Concerns\MigasDeNavegacion;

    protected function migas(): array
    {
        return \App\Filament\Admin\Support\Migas::caso($this->getRecord());
    }

    protected static string $resource = CasoResource::class;

    /**
     * La ficha abre de solo lectura; con "Editar" (?editar=1) se habilitan
     * los campos de "Datos del caso" (ver CasoForm) y aparecen Guardar /
     * Cancelar. Mismo patrón que Personal y Consultas.
     */
    public bool $editando = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Un caso eliminado se puede ver, pero no editar (primero se restaura).
        $this->editando = request()->boolean('editar') && auth()->user()?->puede('editar') && ! $this->getRecord()->trashed();

        $this->abrirSeguimiento(request()->query('seguimiento'));
    }

    /**
     * "Ver" de Seguimiento diario (?seguimiento=realizar) o de Informe
     * seguimiento diario (?seguimiento=informe): abre en Datos judiciales el
     * formulario que corresponde. La tabla de esa pestaña carga después que
     * la página, así que el navegador espera a que esté lista; el argumento
     * "volver" hace que al guardar se regrese a la lista.
     */
    protected function abrirSeguimiento(?string $modo): void
    {
        $accion = match ($modo) {
            'realizar' => ['registrarSeguimiento', 'diario'],
            'informe' => ['registrarInforme', 'informe'],
            default => null,
        };

        if (! $accion || $this->getRecord()->trashed()) {
            return;
        }

        [$nombre, $volver] = $accion;

        $this->js(<<<JS
            (function intentar(veces) {
                const c = window.Livewire?.all().find((c) => c.name.endsWith('SeguimientosRelationManager') && c.el.querySelector('.netley-seg'));
                if (c) {
                    c.\$wire.mountAction('{$nombre}', { volver: '{$volver}' }, { table: true });
                } else if (veces > 0) {
                    setTimeout(() => intentar(veces - 1), 300);
                }
            })(40);
            JS);
    }

    /** El formulario va como primera pestaña, junto a Pagos / Documentación / Seguimiento. */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Datos del proceso';
    }

    public function getContentTabIcon(): string
    {
        return 'heroicon-o-scale';
    }

    /** Datos de la fiscalía / juzgado vigentes (ver Support\DatosInstancia). */
    protected ?array $datosInstancia = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data[DatosInstancia::CAMPO] = DatosInstancia::cargar($this->getRecord());

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->datosInstancia = $data[DatosInstancia::CAMPO] ?? null;
        unset($data[DatosInstancia::CAMPO]);

        return $data;
    }

    protected function afterSave(): void
    {
        DatosInstancia::guardar($this->getRecord(), $this->datosInstancia);
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        abort_unless(auth()->user()?->puede('editar'), 403);

        parent::save(false, $shouldSendSavedNotification);

        $this->editando = false;

        $this->redirect($this->urlFicha());
    }

    protected function getHeaderActions(): array
    {
        // Caso eliminado: solo volver al cliente y restaurar.
        if ($this->getRecord()->trashed()) {
            return [
                Action::make('volverEliminados')
                    ->label('Volver a Casos eliminados')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->url(CasoResource::getUrl('index').'?tab=eliminados'),
                CasoActions::descargarDocumentoEliminacion(),
                CasoActions::restaurar(),
            ];
        }

        // Solo: Editar, Resumen del proceso, Cronología y Cerrar caso (a
        // pedido). Con el caso cerrado, "Reactivar" ocupa el lugar de
        // "Cerrar caso".
        return [
            Action::make('editarCaso')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => ! $this->editando && auth()->user()?->puede('editar'))
                ->action(fn () => $this->redirect($this->urlFicha(editar: true))),
            CasoActions::informeRapido()->label('Resumen del proceso'),
            // Línea de tiempo del caso (antes era una pestaña).
            Action::make('cronologia')
                ->label('Cronología')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->slideOver()
                ->modalWidth('3xl')
                ->modalHeading('Cronología del caso')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalContent(fn (): View => view('filament.admin.casos.cronologia', [
                    'eventos' => CronologiaCaso::eventos($this->getRecord()->fresh()),
                ])),
            CasoActions::cerrar(),
            CasoActions::reactivar(),
        ];
    }

    /**
     * Guardar / Cancelar solo en edición: Filament arma estos botones antes
     * de ejecutar el clic, así que "Editar", "Cancelar" y "Guardar"
     * recargan la ficha.
     */
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

    /** Ficha de este caso; con $editar abre directo en modo edición. */
    protected function urlFicha(bool $editar = false): string
    {
        $url = CasoResource::getUrl('edit', ['record' => $this->getRecord()]);

        return $editar ? $url.'?editar=1' : $url;
    }
}
