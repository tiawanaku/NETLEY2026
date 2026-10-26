<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Guarda un arreglo como texto separado por comas en una sola columna varchar
 * (igual que el sistema legacy), mientras el resto del código (formularios,
 * especialidades relacionadas, etc.) lo trata como un arreglo normal.
 *
 * @implements CastsAttributes<array<int, string>, array<int, string>>
 */
class CsvArray implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return filled($value) ? explode(',', $value) : [];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (blank($value)) {
            return null;
        }

        return implode(',', (array) $value);
    }
}
