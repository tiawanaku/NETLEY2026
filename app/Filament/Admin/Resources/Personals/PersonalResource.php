<?php

namespace App\Filament\Admin\Resources\Personals;

use App\Filament\Admin\Resources\Personals\Pages\CreatePersonal;
use App\Filament\Admin\Resources\Personals\Pages\EditPersonal;
use App\Filament\Admin\Resources\Personals\Pages\ListPersonals;
use App\Filament\Admin\Resources\Personals\Schemas\PersonalForm;
use App\Filament\Admin\Resources\Personals\Tables\PersonalsTable;
use App\Models\Personal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PersonalResource extends Resource
{
    protected static ?string $model = Personal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $slug = 'personal';

    protected static ?string $navigationLabel = 'Personal';

    protected static \UnitEnum|string|null $navigationGroup = 'Personal';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'personal';

    protected static ?string $pluralModelLabel = 'personal';

    protected static ?string $recordTitleAttribute = 'nombres';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('personal') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return PersonalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PersonalsTable::configure($table);
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
            'index' => ListPersonals::route('/'),
            'create' => CreatePersonal::route('/create'),
            'edit' => EditPersonal::route('/{record}/edit'),
        ];
    }
}
