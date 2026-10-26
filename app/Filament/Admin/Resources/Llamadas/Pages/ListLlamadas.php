<?php

namespace App\Filament\Admin\Resources\Llamadas\Pages;

use App\Filament\Admin\Resources\Llamadas\LlamadaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListLlamadas extends ListRecords
{
    protected static string $resource = LlamadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'hoy' => Tab::make('Hoy')
                ->modifyQueryUsing(fn ($query) => $query->whereDate('fecha', now()->toDateString())),
            'pendientes' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn ($query) => $query->whereNull('accion')),
            'futuras' => Tab::make('Futuras')
                ->modifyQueryUsing(fn ($query) => $query->whereDate('fecha', '>', now()->toDateString())),
            'todas' => Tab::make('Todas'),
        ];
    }
}
