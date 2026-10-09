<?php

namespace App\Filament\Admin\Support;

/**
 * Atributos compartidos para los campos de teléfono/WhatsApp en todo el
 * sistema: el "+591" se muestra como prefijo fijo (no editable, pegado al
 * campo) vía ->prefix('+591', isInline: true); el valor que se guarda es
 * solo el número. Se combina con ->regex('/^[0-9]*$/')->maxLength(20) en
 * cada campo que lo use.
 */
class CamposTelefono
{
    public static function atributos(): array
    {
        return [
            'data-enter-nav' => 'true',
            'inputmode' => 'numeric',
            'oninput' => 'this.value=this.value.replace(/[^0-9]/g,\'\')',
        ];
    }
}
