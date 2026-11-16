<?php

namespace App\Filament\Admin\Resources\Citas\Pages;

use App\Filament\Admin\Resources\Citas\CitaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCita extends CreateRecord
{
    // Ruta de navegación por secciones, sin el título repetido (ver Support\Migas).
    use \App\Filament\Admin\Concerns\MigasDeNavegacion;

    protected function migas(): array
    {
        return \App\Filament\Admin\Support\Migas::agenda('lista', 'Nueva cita');
    }

    protected static string $resource = CitaResource::class;
}
