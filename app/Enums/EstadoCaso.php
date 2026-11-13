<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estados del caso: solo Activo, Cerrado y Eliminado (a pedido). Antes
 * había "Activo - Pendiente" y "Reactivado - Pendiente"; los dos pasaron a
 * Activo (ver migración 2026_11_13_000400). "Eliminado" lo pone
 * Caso::booted() al eliminar el caso (va a "Casos eliminados") y al
 * restaurarlo vuelve a Activo.
 */
enum EstadoCaso: string implements HasColor, HasLabel
{
    case Activo = 'activo';
    case Cerrado = 'cerrado';
    case Eliminado = 'eliminado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Cerrado => 'Cerrado',
            self::Eliminado => 'Eliminado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Activo => 'warning',
            self::Cerrado => 'success',
            self::Eliminado => 'danger',
        };
    }
}
