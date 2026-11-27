<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\ListaSeguimientoCasos;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Seguimiento › Seguimiento diario: los casos vigentes con sus actuados a
 * realizar. "Ver" abre Datos judiciales del caso con "Registrar
 * seguimiento" ya abierto; al guardar se vuelve aquí para el próximo cliente.
 */
class SeguimientoDiario extends Page
{
    use \App\Filament\Admin\Concerns\MigasDeNavegacion;
    use ListaSeguimientoCasos;

    protected function migas(): array
    {
        return [['Seguimiento'], ['Seguimiento diario']];
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Seguimiento diario';

    // Grupo propio "Seguimiento", después de Pagos y antes de Agenda (50),
    // junto a "Informe seguimiento diario" (InformeSeguimientoDiario).
    protected static \UnitEnum|string|null $navigationGroup = 'Seguimiento';

    protected static ?int $navigationSort = 45;

    protected static ?string $slug = 'seguimiento-diario';

    protected string $view = 'filament.admin.pages.lista-seguimiento';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('seguimiento_diario') ?? false;
    }

    public function getTitle(): string
    {
        return 'Seguimiento diario';
    }

    public function modo(): string
    {
        return 'realizar';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imprimir')
                ->label('Imprimir actuados a realizar')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(fn () => $this->imprimir('Seguimiento diario')),
        ];
    }
}
