<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoPersonal: string implements HasColor, HasLabel
{
    case Habilitado = 'habilitado';
    case Inhabilitado = 'inhabilitado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Habilitado => 'Habilitado',
            self::Inhabilitado => 'Inhabilitado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Habilitado => 'success',
            self::Inhabilitado => 'danger',
        };
    }
}
