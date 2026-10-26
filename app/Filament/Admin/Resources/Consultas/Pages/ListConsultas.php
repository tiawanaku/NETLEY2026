<?php

namespace App\Filament\Admin\Resources\Consultas\Pages;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Consultas\ConsultaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

class ListConsultas extends ListRecords
{
    protected static string $resource = ConsultaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Las pestañas Nuevas/Agendados/Cerradas solo se navegan desde el menú
     * lateral (ver ConsultaResource::getNavigationItems()); acá se omite el
     * componente de pestañas para no repetirlas arriba de la tabla.
     * getTabs() se mantiene igual: sigue filtrando la tabla según el ?tab=
     * de la URL, solo que sin dibujar la barra de pestañas.
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
            // Igual que la vista de Agenda (AgendaCalendario::citasQuery()):
            // cualquier consulta con una cita vigente (no anulada), sin
            // importar si su estado ya avanzó después de la cita (ej. pasó a
            // "Caso Iniciado"). Si se filtrara por estado = Agendado, las
            // citas antiguas desaparecerían de acá apenas la consulta avanza.
            'agendados' => Tab::make('Agendados')
                ->modifyQueryUsing(fn ($query) => $query->whereHas('citas', fn ($q) => $q->where('anulada', false))
                    // Trae la cita vigente de cada consulta (ver ConsultasTable,
                    // que en esta pestaña muestra los datos de agenda en vez de
                    // los de la consulta).
                    ->with(['citas' => fn ($q) => $q->where('anulada', false)->orderByDesc('fecha')->orderByDesc('hora')])),
            'cerradas' => Tab::make('Consultas cerradas')
                ->modifyQueryUsing(fn ($query) => $query->whereNotIn('estado', $abiertos)),
        ];
    }
}
