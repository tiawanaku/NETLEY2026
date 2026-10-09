<?php

namespace App\Filament\Admin\Resources\Tallers;

use App\Filament\Admin\Resources\Tallers\Pages\CreateTaller;
use App\Filament\Admin\Resources\Tallers\Pages\EditTaller;
use App\Filament\Admin\Resources\Tallers\Pages\ListTallers;
use App\Filament\Admin\Resources\Tallers\Schemas\TallerForm;
use App\Filament\Admin\Resources\Tallers\Tables\TallersTable;
use App\Models\Taller;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TallerResource extends Resource
{
    protected static ?string $model = Taller::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $slug = 'talleres';

    protected static ?string $navigationLabel = 'Talleres';

    protected static \UnitEnum|string|null $navigationGroup = 'Llamadas';

    protected static ?int $navigationSort = 61;

    protected static ?string $recordTitleAttribute = 'nombres';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('talleres') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return TallerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TallersTable::configure($table);
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
            'index' => ListTallers::route('/'),
            'create' => CreateTaller::route('/create'),
            'edit' => EditTaller::route('/{record}/edit'),
        ];
    }
}
