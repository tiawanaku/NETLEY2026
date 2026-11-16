<?php

namespace App\Filament\Admin\Resources\ImagenesCarrusel\Pages;

use App\Filament\Admin\Resources\ImagenesCarrusel\ImagenCarruselResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageImagenesCarrusel extends ManageRecords
{
    use \App\Filament\Admin\Concerns\MigasSimples;

    protected static string $resource = ImagenCarruselResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
