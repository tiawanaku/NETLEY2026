<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoDocumento: string implements HasLabel
{
    case Fotocopia = 'fotocopia';
    case FotocopiaLegalizada = 'fotocopia_legalizada';
    case Fojas = 'fojas';
    case Original = 'original';
    case Otro = 'otro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fotocopia => 'Fotocopia',
            self::FotocopiaLegalizada => 'Fotocopia legalizada',
            self::Fojas => 'Fojas',
            self::Original => 'Original',
            self::Otro => 'Otro',
        };
    }
}
