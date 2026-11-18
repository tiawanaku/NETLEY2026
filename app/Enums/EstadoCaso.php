<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estados del caso: Activo, Cerrado, Pendiente y Eliminado. Antes
 * había "Activo - Pendiente" y "Reactivado - Pendiente"; los dos pasaron a
 * Activo (ver migración 2026_11_13_000400). "Eliminado" lo pone
 * Caso::booted() al eliminar el caso (va a "Casos eliminados") y al
 * restaurarlo vuelve a Activo.
 *
 * "Pendiente": caso cerrado (ganado) cuyo proceso de costas y costos sigue
 * abierto; vuelve a Cerrado cuando se cierra el de costas (ver
 * Caso::sincronizarOrigenCostas()). Cuenta como cerrado en listados y
 * contadores (ver cerrados()).
 */
enum EstadoCaso: string implements HasColor, HasLabel
{
    case Activo = 'activo';
    case Cerrado = 'cerrado';
    case Pendiente = 'pendiente';
    case Eliminado = 'eliminado';

    /** Estados que cuentan como caso cerrado (pestaña "Casos cerrados", contadores). */
    public static function cerrados(): array
    {
        return [self::Cerrado, self::Pendiente];
    }

    public function esCerrado(): bool
    {
        return in_array($this, self::cerrados(), true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Cerrado => 'Cerrado',
            self::Pendiente => 'Pendiente',
            self::Eliminado => 'Eliminado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Activo => 'warning',
            self::Cerrado => 'success',
            self::Pendiente => 'info',
            self::Eliminado => 'danger',
        };
    }
}
