<?php

namespace App\Filament\Admin\Resources\Casos\Pages;

use App\Filament\Admin\Resources\Casos\Actions\CasoActions;
use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Filament\Admin\Resources\Clientes\ClienteResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCaso extends EditRecord
{
    protected static string $resource = CasoResource::class;

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
            CasoActions::agendarCita(),
            CasoActions::realizarPago(),
            CasoActions::informeRapido(),
            CasoActions::cerrar(),
            CasoActions::reactivar(),
            CasoActions::informeCierrePdf(),
            DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
        ];
    }
}
