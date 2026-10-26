<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoCuota: string implements HasColor, HasLabel
{
    case Pendiente = 'pendiente';
    case Pagado = 'pagado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Pagado => 'Pagado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Pagado => 'success',
        };
    }
}
