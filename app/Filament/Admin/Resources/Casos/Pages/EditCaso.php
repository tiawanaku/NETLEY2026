<?php

namespace App\Filament\Admin\Resources\Casos\Pages;

use App\Filament\Admin\Resources\Casos\Actions\CasoActions;
use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Filament\Admin\Resources\Clientes\ClienteResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditCaso extends EditRecord
{
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

        $this->editando = request()->boolean('editar') && auth()->user()?->puede('editar');
    }

    /** El formulario va como primera pestaña, junto a Pagos / Documentación / Seguimiento. */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Datos del caso';
    }

    public function getContentTabIcon(): string
    {
        return 'heroicon-o-scale';
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
        return [
            // Vuelve a la ficha del cliente (pestaña "Casos").
            Action::make('verCliente')
                ->label('Volver al cliente')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn () => $this->getRecord()->cliente !== null)
                ->url(fn () => ClienteResource::getUrl('edit', ['record' => $this->getRecord()->cliente_id])),
            Action::make('editarCaso')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => ! $this->editando && auth()->user()?->puede('editar'))
                ->action(fn () => $this->redirect($this->urlFicha(editar: true))),
            // Sin "Realizar pago" ni "Borrar" en la ficha (a pedido): cada
            // cuota se paga desde la pestaña Pagos (Plan de pagos).
            CasoActions::agendarCita(),
            CasoActions::informeRapido(),
            CasoActions::cerrar(),
            CasoActions::reactivar(),
            CasoActions::informeCierrePdf(),
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
