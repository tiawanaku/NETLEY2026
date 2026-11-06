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
            // Solo en "Nuevas": en Agendados/Cerradas no corresponde crear
            // una consulta directamente desde ahí.
            CreateAction::make()
                ->visible(fn () => in_array($this->activeTab, [null, 'nuevas'], true)),
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
        $abiertos = array_map(fn ($e) => $e->value, EstadoConsulta::abiertos());

        return [
            // No cerradas y sin ninguna cita vigente — sin importar el estado
            // (ej. "Agendado" sin cita, si se canceló el modal de Agendar sin
            // llegar a guardarla): mientras no tenga cita, sigue siendo una
            // consulta nueva por atender, no queda invisible en ninguna
            // pestaña.
            'nuevas' => Tab::make('Consultas nuevas')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('estado', $abiertos)
                    ->whereDoesntHave('citas', fn ($q) => $q->where('anulada', false))),
            // Solo consultas (leads) con una cita vigente (no anulada) que
            // todavía NO se ascendieron a cliente ejecutivo: una vez que
            // caso_id queda definido, sus citas pasan a verse en la Agenda
            // de Cliente Ejecutivo (ver App\Filament\Admin\Pages\AgendaClientes),
            // no acá. Si la consulta ya se cerró (estado no abierto), tampoco
            // corresponde seguir viéndola acá aunque conserve una cita
            // vigente: pasa a "Cerradas".
            'agendados' => Tab::make('Agendados')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('estado', $abiertos)
                    ->whereNull('caso_id')
                    ->whereHas('citas', fn ($q) => $q->where('anulada', false))
                    // F.Cita/Hora/Abogado de la tabla (ver ConsultasTable)
                    // usan esto; se precarga para no consultar por fila.
                    ->with('citaVigente.personal')),
            // Cerradas que NO se ascendieron a cliente ejecutivo: esas ya
            // tienen su propio seguimiento como caso, no corresponde seguir
            // viéndolas acá como "consulta cerrada". Se excluye el estado
            // "Caso Iniciado" explícitamente además de por caso_id: un
            // registro con ese estado pero sin caso_id (dato inconsistente)
            // tampoco debe aparecer acá.
            'cerradas' => Tab::make('Consultas cerradas')
                ->modifyQueryUsing(fn ($query) => $query->whereNotIn('estado', $abiertos)
                    ->where('estado', '!=', EstadoConsulta::CasoIniciado)
                    ->whereNull('caso_id')),
        ];
    }
}
