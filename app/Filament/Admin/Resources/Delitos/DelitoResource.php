<?php

namespace App\Filament\Admin\Resources\Delitos;

use App\Filament\Admin\Resources\Delitos\Pages\CreateDelito;
use App\Filament\Admin\Resources\Delitos\Pages\EditDelito;
use App\Filament\Admin\Resources\Delitos\Pages\ListDelitos;
use App\Filament\Admin\Resources\Delitos\Schemas\DelitoForm;
use App\Filament\Admin\Resources\Delitos\Tables\DelitosTable;
use App\Models\Delito;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DelitoResource extends Resource
{
    protected static ?string $model = Delito::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Delitos';

    protected static \UnitEnum|string|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 80;

    protected static ?string $modelLabel = 'delito';

    protected static ?string $pluralModelLabel = 'delitos';

    protected static ?string $recordTitleAttribute = 'delito';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('delitos') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return DelitoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DelitosTable::configure($table);
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
            'index' => ListDelitos::route('/'),
            'create' => CreateDelito::route('/create'),
            'edit' => EditDelito::route('/{record}/edit'),
        ];
    }
}
