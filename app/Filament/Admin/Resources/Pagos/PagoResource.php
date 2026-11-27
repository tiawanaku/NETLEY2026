<?php

namespace App\Filament\Admin\Resources\Pagos;

use App\Filament\Admin\Resources\Pagos\Pages\ListPagos;
use App\Filament\Admin\Resources\Pagos\Tables\PagosTable;
use App\Models\Pago;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PagoResource extends Resource
{
    protected static ?string $model = Pago::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Pagos';

    protected static \UnitEnum|string|null $navigationGroup = 'Pagos';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'nro_recibo';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('pagos') ?? false;
    }

    /**
     * Grupo "Pagos" del menú (simple y completo): "Resumen de pagos"
     * (historial de pagos). "Nuevo pago" es su propia página (Pages\NuevoPago):
     * se cobra desde el plan de pagos del cliente, no con un formulario suelto.
     */
    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make('Resumen de pagos')
                ->group(static::getNavigationGroup())
                ->icon('heroicon-o-banknotes')
                ->sort(static::getNavigationSort() + 1)
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.index'))
                ->url(static::getUrl('index')),
        ];
    }

    /** Registros financieros inmutables una vez emitido el recibo: no hay página de edición. */
    public static function canEdit($record): bool
    {
        return false;
    }

    /** Los pagos se registran desde el plan de pagos (Pagos › Nuevo pago o la pestaña Pagos del caso). */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return PagosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPagos::route('/'),
        ];
    }
}
