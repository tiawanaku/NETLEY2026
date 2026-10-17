<?php

namespace App\Filament\Admin\Support;

/**
 * Interruptor del menú lateral: "simple" (por defecto) muestra solo un
 * grupo reducido de opciones; apagado, muestra el menú completo de
 * siempre. Se guarda en una cookie (sin login de por medio), así que es
 * por navegador, no por usuario.
 */
class ModoMenu
{
    public const COOKIE = 'menu_simple';

    public static function esSimple(): bool
    {
        $valor = request()->cookie(self::COOKIE);

        // Sin cookie todavía (primera visita): simple, que es el default pedido.
        return $valor === null || $valor === '1';
    }
}
