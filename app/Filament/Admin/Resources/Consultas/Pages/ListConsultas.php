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
        // "Nuevas" = abiertos salvo Agendado, que tiene su propia pestaña
        // (y su propio ítem en el menú lateral cuando está en modo simple,
        // ver App\Filament\Admin\Support\ModoMenu).
        $nuevas = array_map(
            fn ($e) => $e->value,
            array_filter(EstadoConsulta::abiertos(), fn ($e) => $e !== EstadoConsulta::Agendado),
        );
        $abiertos = array_map(fn ($e) => $e->value, EstadoConsulta::abiertos());

        return [
            'nuevas' => Tab::make('Consultas nuevas')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('estado', $nuevas)),
            'agendados' => Tab::make('Agendados')
                ->modifyQueryUsing(fn ($query) => $query->where('estado', EstadoConsulta::Agendado->value)),
            'cerradas' => Tab::make('Consultas cerradas')
                ->modifyQueryUsing(fn ($query) => $query->whereNotIn('estado', $abiertos)),
        ];
    }
}
