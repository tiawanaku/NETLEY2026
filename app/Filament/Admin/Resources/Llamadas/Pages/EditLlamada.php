<?php

namespace App\Filament\Admin\Resources\Llamadas\Pages;

use App\Filament\Admin\Resources\Llamadas\Actions\CrearConsultaAction;
use App\Filament\Admin\Resources\Llamadas\LlamadaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLlamada extends EditRecord
{
    protected static string $resource = LlamadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CrearConsultaAction::make(),
            DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
        ];
    }
}
