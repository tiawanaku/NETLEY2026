<?php

namespace App\Filament\Admin\Resources\Contactos\Pages;

use App\Filament\Admin\Resources\Contactos\ContactoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContacto extends CreateRecord
{
    use \App\Filament\Admin\Concerns\MigasSimples;

    protected static string $resource = ContactoResource::class;
}
