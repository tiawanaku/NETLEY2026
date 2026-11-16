<?php

namespace App\Filament\Admin\Concerns;

use App\Filament\Admin\Support\Migas;
use Illuminate\Contracts\Support\Htmlable;

/**
 * La pantalla muestra arriba solo la ruta de navegación por secciones (ver
 * App\Filament\Admin\Support\Migas), sin el título grande en negrita que
 * repetía el último escalón. El título sigue en la pestaña del navegador
 * (getTitle()).
 *
 * Cada página define migas(): la lista de escalones [texto, url].
 */
trait MigasDeNavegacion
{
    /** @return array<int, array{0: string, 1?: ?string}> */
    abstract protected function migas(): array;

    public function getBreadcrumbs(): array
    {
        return Migas::armar($this->migas());
    }

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }
}
