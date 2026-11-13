<?php

namespace App\Filament\Admin\Resources\Casos\Pages;

use App\Enums\EstadoCaso;
use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Filament\Admin\Support\ModoMenu;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

/**
 * Listado de casos con pestañas: Todos / Casos cerrados / Casos eliminados.
 * En modo simple (ver ModoMenu) cada una se abre desde su ítem del menú
 * lateral (?tab=...) y la barra de pestañas no se dibuja, igual que en
 * Consultas.
 */
class ListCasos extends ListRecords
{
    protected static string $resource = CasoResource::class;

    public function getTitle(): string
    {
        return match ($this->activeTab) {
            'cerrados' => 'Casos cerrados',
            'eliminados' => 'Casos eliminados',
            default => 'Casos',
        };
    }

    public function content(Schema $schema): Schema
    {
        if (! ModoMenu::esSimple()) {
            return parent::content($schema);
        }

        return $schema
            ->components([
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    public function getTabs(): array
    {
        $tabs = [
            'todos' => Tab::make('Todos'),
            'cerrados' => Tab::make('Casos cerrados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', EstadoCaso::Cerrado)),
        ];

        if (auth()->user()?->puede('eliminar')) {
            $tabs['eliminados'] = Tab::make('Casos eliminados')
                ->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed());
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo proceso')
                ->visible(fn () => CasoResource::canCreate() && ! ModoMenu::esSimple()),
        ];
    }
}
