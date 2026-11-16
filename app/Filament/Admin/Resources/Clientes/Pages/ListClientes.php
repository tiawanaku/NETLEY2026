<?php

namespace App\Filament\Admin\Resources\Clientes\Pages;

use App\Filament\Admin\Resources\Clientes\ClienteResource;
use Filament\Resources\Pages\ListRecords;

class ListClientes extends ListRecords
{
    // Ruta de navegación por secciones, sin el título repetido (ver Support\Migas).
    use \App\Filament\Admin\Concerns\MigasDeNavegacion;

    protected function migas(): array
    {
        return \App\Filament\Admin\Support\Migas::clientes();
    }

    protected static string $resource = ClienteResource::class;

    /**
     * Sin botón "Crear cliente" (a pedido): los clientes ejecutivos se dan
     * de alta desde una consulta (motivo "Cliente ejecutivo"), y un caso
     * nuevo para un cliente existente desde su ficha ("Nuevo proceso").
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
