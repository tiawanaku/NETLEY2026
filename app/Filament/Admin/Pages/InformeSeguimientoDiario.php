<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\ListaSeguimientoCasos;
use App\Filament\Admin\Concerns\MigasDeNavegacion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Seguimiento › Informe seguimiento diario: los casos vigentes con sus
 * actuados pendientes y los realizados hoy. "Ver" abre Datos judiciales
 * del caso con "Registrar actuados realizados" ya abierto; al guardar se
 * vuelve aquí para el próximo cliente.
 */
class InformeSeguimientoDiario extends Page
{
    use ListaSeguimientoCasos;
    use MigasDeNavegacion;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Informe seguimiento diario';

    protected static \UnitEnum|string|null $navigationGroup = 'Seguimiento';

    protected static ?int $navigationSort = 46;

    protected static ?string $slug = 'seguimiento/informe-diario';

    protected string $view = 'filament.admin.pages.lista-seguimiento';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('seguimiento_diario') ?? false;
    }

    public function getTitle(): string
    {
        return 'Informe seguimiento diario';
    }

    protected function migas(): array
    {
        return [['Seguimiento'], ['Informe seguimiento diario']];
    }

    public function modo(): string
    {
        return 'informe';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imprimir')
                ->label('Imprimir informe del día')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(fn () => $this->imprimir('Informe seguimiento diario')),
        ];
    }
}
