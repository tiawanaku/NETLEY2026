<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Especialidad: string implements HasLabel
{
    case Civil = 'CIVIL';
    case Penal = 'PENAL';
    case Familia = 'FAMILIA';
    case Laboral = 'LABORAL';

    public function getLabel(): string
    {
        return match ($this) {
            self::Civil => 'Civil',
            self::Penal => 'Penal',
            self::Familia => 'Familia',
            self::Laboral => 'Laboral',
        };
    }
}
