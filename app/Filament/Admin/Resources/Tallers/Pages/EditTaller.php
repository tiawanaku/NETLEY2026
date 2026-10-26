<?php

namespace App\Filament\Admin\Resources\Tallers\Pages;

use App\Filament\Admin\Resources\Tallers\TallerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTaller extends EditRecord
{
    protected static string $resource = TallerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
        ];
    }
}
