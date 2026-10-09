<?php

namespace App\Filament\Admin\Resources\Consultas\Pages;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Consultas\ConsultaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListConsultas extends ListRecords
{
    protected static string $resource = ConsultaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $abiertos = array_map(fn ($e) => $e->value, EstadoConsulta::abiertos());

        return [
            'nuevas' => Tab::make('Consultas nuevas')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('estado', $abiertos)),
            'cerradas' => Tab::make('Consultas cerradas')
                ->modifyQueryUsing(fn ($query) => $query->whereNotIn('estado', $abiertos)),
        ];
    }
}
