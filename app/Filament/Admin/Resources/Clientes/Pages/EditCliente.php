<?php

namespace App\Filament\Admin\Resources\Clientes\Pages;

use App\Filament\Admin\Resources\Clientes\Actions\ClienteActions;
use App\Filament\Admin\Resources\Clientes\ClienteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

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
