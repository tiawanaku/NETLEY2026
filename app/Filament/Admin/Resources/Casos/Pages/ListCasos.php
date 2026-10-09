<?php

namespace App\Filament\Admin\Resources\Casos\Pages;

use App\Filament\Admin\Resources\Casos\CasoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCasos extends ListRecords
{
    protected static string $resource = CasoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo proceso')->visible(fn () => CasoResource::canCreate()),
        ];
    }
}
