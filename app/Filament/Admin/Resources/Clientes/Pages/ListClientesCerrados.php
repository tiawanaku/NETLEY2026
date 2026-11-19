<?php

namespace App\Filament\Admin\Resources\Clientes\Pages;

use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Filament\Admin\Resources\Clientes\Tables\ClientesTable;
use Filament\Tables\Table;

/**
 * "Casos cerrados": la misma tabla de Clientes ejecutivos, solo con los
 * clientes que tienen algún caso cerrado. Al entrar a uno, su lista de
 * procesos muestra solo los cerrados (EditCliente con ?cerrados=1).
 * casos?tab=cerrados (menú) redirige acá, ver ListCasos::mount().
 */
class ListClientesCerrados extends ListClientes
{
    protected static ?string $title = 'Casos cerrados';

    protected function migas(): array
    {
        return [['Cliente Ejecutivo'], ['Casos cerrados']];
    }

    public function getTitle(): string
    {
        return 'Casos cerrados';
    }

    public function table(Table $table): Table
    {
        return ClientesTable::configure($table, soloCerrados: true);
    }

    public static function canAccess(array $parameters = []): bool
    {
        return CasoResource::canViewAny();
    }
}
