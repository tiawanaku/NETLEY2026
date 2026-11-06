<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FormaIngreso: string implements HasLabel
{
    case Directo = 'directo';
    case PaginaWeb = 'pagina_web';
    case Whatsapp = 'whatsapp';

    public function getLabel(): string
    {
        return match ($this) {
            self::Directo => 'Directo',
            self::PaginaWeb => 'Página web',
            self::Whatsapp => 'WhatsApp',
        };
    }
}
