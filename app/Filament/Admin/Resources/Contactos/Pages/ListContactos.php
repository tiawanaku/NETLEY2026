<?php

namespace App\Filament\Admin\Resources\Contactos\Pages;

use App\Filament\Admin\Resources\Contactos\ContactoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContactos extends ListRecords
{
    use \App\Filament\Admin\Concerns\MigasSimples;

    protected static string $resource = ContactoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
