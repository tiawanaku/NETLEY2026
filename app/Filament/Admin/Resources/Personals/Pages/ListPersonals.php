<?php

namespace App\Filament\Admin\Resources\Personals\Pages;

use App\Filament\Admin\Resources\Personals\PersonalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPersonals extends ListRecords
{
    protected static string $resource = PersonalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Agregar personal'),
        ];
    }
}
