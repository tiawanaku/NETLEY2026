<?php

namespace App\Filament\Admin\Resources\Contactos;

use App\Filament\Admin\Resources\Contactos\Pages\CreateContacto;
use App\Filament\Admin\Resources\Contactos\Pages\EditContacto;
use App\Filament\Admin\Resources\Contactos\Pages\ListContactos;
use App\Filament\Admin\Resources\Contactos\Schemas\ContactoForm;
use App\Filament\Admin\Resources\Contactos\Tables\ContactosTable;
use App\Models\Contacto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContactoResource extends Resource
{
    protected static ?string $model = Contacto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Contactos';

    protected static \UnitEnum|string|null $navigationGroup = 'Personal';

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('contactos') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return ContactoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactosTable::configure($table);
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
            'index' => ListContactos::route('/'),
            'create' => CreateContacto::route('/create'),
            'edit' => EditContacto::route('/{record}/edit'),
        ];
    }
}
