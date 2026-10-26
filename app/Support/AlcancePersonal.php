<?php

namespace App\Support;

use App\Enums\Rol;
use App\Models\Personal;
use App\Models\User;

/**
 * A qué ids de `personal` debe limitarse la vista de un usuario: un Abogado
 * ve solo lo suyo; una Procuradora ve lo de los abogados que tiene asignados
 * (ver Personal::abogadosAsignados()); el resto de roles no tiene
 * restricción. Usado tanto por ScopesToAssignedAbogado (listados) como por
 * los widgets del Escritorio.
 */
class AlcancePersonal
{
    /**
     * @return array<int, int>|null null = sin restricción, ve todo
     */
    public static function idsRelevantes(?User $user): ?array
    {
        if (! $user || ! $user->personal_id) {
            return null;
        }

        if ($user->hasRole(Rol::Abogado)) {
            return [$user->personal_id];
        }

        if ($user->hasRole(Rol::Procurador)) {
            return Personal::find($user->personal_id)?->abogadosAsignados()->pluck('personal.id')->all() ?? [];
        }

        return null;
    }
}
