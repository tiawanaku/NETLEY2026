<?php

namespace App\Filament\Admin\Resources\Citas\Pages;

use App\Filament\Admin\Resources\Citas\Actions\AnularCitaAction;
use App\Filament\Admin\Resources\Citas\CitaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCita extends EditRecord
{
    // Ruta de navegación por secciones, sin el título repetido (ver Support\Migas).
    use \App\Filament\Admin\Concerns\MigasDeNavegacion;

    protected function migas(): array
    {
        return \App\Filament\Admin\Support\Migas::agenda('lista', 'Cita del '.$this->getRecord()->fecha?->format('d/m/Y'));
    }

    protected static string $resource = CitaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AnularCitaAction::make(),
            DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
        ];
    }
}
