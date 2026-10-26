<?php

namespace App\Filament\Admin\Resources\RedesSociales\Pages;

use App\Filament\Admin\Resources\RedesSociales\RedSocialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRedesSociales extends ManageRecords
{
    protected static string $resource = RedSocialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
