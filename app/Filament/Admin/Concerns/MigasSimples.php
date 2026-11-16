<?php

namespace App\Filament\Admin\Concerns;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;

/**
 * Ruta de navegación para los catálogos y demás recursos sin secciones
 * propias en el menú: "Delitos › Robo agravado" en la ficha, "Delitos ›
 * Nuevo" al crear y solo "Delitos" en el listado; sin el título grande que
 * repetía el último escalón (ver MigasDeNavegacion).
 */
trait MigasSimples
{
    use MigasDeNavegacion;

    protected function migas(): array
    {
        $resource = static::getResource();
        $etiqueta = $resource::getBreadcrumb();

        if ($this instanceof EditRecord || $this instanceof ViewRecord) {
            return [[$etiqueta, $resource::getUrl('index')], [(string) $this->getRecordTitle()]];
        }

        if ($this instanceof CreateRecord) {
            return [[$etiqueta, $resource::getUrl('index')], ['Nuevo']];
        }

        return [[$etiqueta]];
    }
}
