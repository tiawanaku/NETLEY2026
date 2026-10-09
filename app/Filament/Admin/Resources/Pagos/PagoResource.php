<?php

namespace App\Filament\Admin\Resources\Pagos;

use App\Filament\Admin\Resources\Pagos\Pages\CreatePago;
use App\Filament\Admin\Resources\Pagos\Pages\ListPagos;
use App\Filament\Admin\Resources\Pagos\Schemas\PagoForm;
use App\Filament\Admin\Resources\Pagos\Tables\PagosTable;
use App\Models\Pago;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
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

    /** Registros financieros inmutables una vez emitido el recibo: no hay página de edición. */
    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PagoForm::configure($schema);
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
            'create' => CreatePago::route('/create'),
        ];
    }
}
