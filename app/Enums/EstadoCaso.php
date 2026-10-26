<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoCaso: string implements HasColor, HasLabel
{
    case ActivoPendiente = 'activo_pendiente';
    case ReactivadoPendiente = 'reactivado_pendiente';
    case Cerrado = 'cerrado';

    public function getLabel(): string
    {
        return match ($this) {
            self::ActivoPendiente => 'Activo - Pendiente',
            self::ReactivadoPendiente => 'Reactivado - Pendiente',
            self::Cerrado => 'Cerrado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ActivoPendiente => 'warning',
            self::ReactivadoPendiente => 'info',
            self::Cerrado => 'success',
        };
    }
}
