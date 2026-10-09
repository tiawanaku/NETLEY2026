<?php

namespace App\Filament\Admin\Resources\Llamadas\Pages;

use App\Filament\Admin\Resources\Llamadas\LlamadaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLlamada extends CreateRecord
{
    protected static string $resource = LlamadaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creado_por'] = auth()->user()?->username;

        return $data;
    }
}
