<?php

namespace App\Filament\Admin\Resources\Delitos\Pages;

use App\Filament\Admin\Resources\Delitos\DelitoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDelito extends EditRecord
{
    use \App\Filament\Admin\Concerns\MigasSimples;

    protected static string $resource = DelitoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
        ];
    }
}
