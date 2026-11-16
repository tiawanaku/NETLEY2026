<?php

namespace App\Filament\Admin\Resources\Casos\Pages;

use App\Filament\Admin\Resources\Casos\CasoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCaso extends CreateRecord
{
    // Ruta de navegación por secciones, sin el título repetido (ver Support\Migas).
    use \App\Filament\Admin\Concerns\MigasDeNavegacion;

    protected function migas(): array
    {
        return \App\Filament\Admin\Support\Migas::clientes(null, 'Nuevo proceso');
    }

    protected static string $resource = CasoResource::class;
}
