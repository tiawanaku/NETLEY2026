<?php

namespace App\Filament\Admin\Resources\Delitos\Pages;

use App\Enums\Especialidad;
use App\Filament\Admin\Resources\Delitos\DelitoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListDelitos extends ListRecords
{
    protected static string $resource = DelitoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Todos')];

        foreach (Especialidad::cases() as $especialidad) {
            $tabs[$especialidad->value] = Tab::make($especialidad->getLabel())
                ->modifyQueryUsing(fn ($query) => $query->where('area', $especialidad->value));
        }

        return $tabs;
    }
}
