<?php

namespace App\Filament\Admin\Resources\Municipios\Pages;

use App\Filament\Admin\Resources\Municipios\MunicipioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMunicipios extends ManageRecords
{
    use \App\Filament\Admin\Concerns\MigasSimples;

    protected static string $resource = MunicipioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
