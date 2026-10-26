<?php

namespace App\Filament\Admin\Concerns;

use App\Support\AlcancePersonal;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restringe el listado a los registros donde el abogado autenticado está
 * asignado (relación `personal`). Una procuradora ve los registros de los
 * abogados que tiene asignados (ver Personal::abogadosAsignados()), sin
 * necesidad de asignarle cada caso uno por uno. El resto de roles ve todos
 * los registros, igual que en el resto del panel.
 */
trait ScopesToAssignedAbogado
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $ids = AlcancePersonal::idsRelevantes(auth()->user());

        if ($ids !== null) {
            $query->whereHas('personal', fn (Builder $q) => $q->whereIn('personal.id', $ids));
        }

        return $query;
    }
}
