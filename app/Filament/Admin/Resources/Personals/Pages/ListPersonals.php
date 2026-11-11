<?php

namespace App\Filament\Admin\Resources\Personals\Pages;

use App\Filament\Admin\Resources\Personals\PersonalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

class ListPersonals extends ListRecords
{
    protected static string $resource = PersonalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Agregar personal')
                ->visible(fn (): bool => $this->activeTab !== 'retirados'),
        ];
    }

    /**
     * Las pestañas Vigentes/Retirados solo se navegan desde el
     * menú lateral (ver PersonalResource::getNavigationItems()); acá se
     * omite el componente de pestañas para no repetirlas arriba de la
     * tabla. getTabs() se mantiene igual: sigue filtrando la tabla según el
     * ?tab= de la URL, solo que sin dibujar la barra de pestañas.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    public function getTabs(): array
    {
        return [
            // Vigentes: todo el personal no eliminado, habilitado o
            // deshabilitado. Retirados: los eliminados (borrado lógico).
            'vigentes' => Tab::make('Vigentes')
                ->modifyQueryUsing(fn ($query) => $query->withoutTrashed()),
            'retirados' => Tab::make('Retirados')
                ->modifyQueryUsing(fn ($query) => $query->onlyTrashed()),
        ];
    }
}
