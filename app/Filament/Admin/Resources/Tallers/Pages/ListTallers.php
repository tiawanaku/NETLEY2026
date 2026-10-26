<?php

namespace App\Filament\Admin\Resources\Tallers\Pages;

use App\Filament\Admin\Resources\Tallers\TallerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTallers extends ListRecords
{
    protected static string $resource = TallerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
