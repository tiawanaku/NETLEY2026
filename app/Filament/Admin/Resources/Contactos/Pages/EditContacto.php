<?php

namespace App\Filament\Admin\Resources\Contactos\Pages;

use App\Filament\Admin\Resources\Contactos\ContactoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContacto extends EditRecord
{
    protected static string $resource = ContactoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
        ];
    }
}
