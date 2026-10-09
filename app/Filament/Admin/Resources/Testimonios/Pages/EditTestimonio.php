<?php

namespace App\Filament\Admin\Resources\Testimonios\Pages;

use App\Filament\Admin\Resources\Testimonios\TestimonioResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTestimonio extends EditRecord
{
    protected static string $resource = TestimonioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['fecha_modificacion'] = now();
        $data['modificado_por'] = auth()->user()?->username;

        return $data;
    }
}
