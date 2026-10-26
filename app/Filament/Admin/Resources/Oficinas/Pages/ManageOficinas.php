<?php

namespace App\Filament\Admin\Resources\Oficinas\Pages;

use App\Filament\Admin\Resources\Oficinas\OficinaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOficinas extends ManageRecords
{
    protected static string $resource = OficinaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
