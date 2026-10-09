<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Rol: int implements HasLabel
{
    case Master = 0;
    case Administrador = 1;
    case Finanzas = 2;
    case Abogado = 3;
    case Secretaria = 4;
    case Pasante = 5;
    case Procurador = 6;

    public function getLabel(): string
    {
        return match ($this) {
            self::Master => 'Master',
            self::Administrador => 'Administrador',
            self::Finanzas => 'Finanzas',
            self::Abogado => 'Abogado',
            self::Secretaria => 'Secretaria',
            self::Pasante => 'Pasante',
            self::Procurador => 'Procuradora',
        };
    }
}
