<?php

namespace App\Filament\Admin\Resources\Casos\Pages;

use App\Filament\Admin\Resources\Casos\Actions\CasoActions;
use App\Filament\Admin\Resources\Casos\CasoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCaso extends EditRecord
{
    protected static string $resource = CasoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CasoActions::agendarCita(),
            CasoActions::realizarPago(),
            CasoActions::informeRapido(),
            CasoActions::cerrar(),
            CasoActions::reactivar(),
            CasoActions::informeCierrePdf(),
            DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
        ];
    }
}
