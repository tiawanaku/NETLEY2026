<?php

namespace App\Filament\Admin\Resources\Llamadas;

use App\Filament\Admin\Concerns\ScopesToAssignedAbogado;
use App\Filament\Admin\Resources\Llamadas\Pages\CreateLlamada;
use App\Filament\Admin\Resources\Llamadas\Pages\EditLlamada;
use App\Filament\Admin\Resources\Llamadas\Pages\ListLlamadas;
use App\Filament\Admin\Resources\Llamadas\RelationManagers\IntentosLlamadaRelationManager;
use App\Filament\Admin\Resources\Llamadas\Schemas\LlamadaForm;
use App\Filament\Admin\Resources\Llamadas\Tables\LlamadasTable;
use App\Models\Llamada;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LlamadaResource extends Resource
{
    use ScopesToAssignedAbogado;

    protected static ?string $model = Llamada::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = 'Llamadas';

    protected static \UnitEnum|string|null $navigationGroup = 'Llamadas';

    protected static ?int $navigationSort = 60;

    protected static ?string $recordTitleAttribute = 'nombres';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('llamadas') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return LlamadaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LlamadasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            IntentosLlamadaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLlamadas::route('/'),
            'create' => CreateLlamada::route('/create'),
            'edit' => EditLlamada::route('/{record}/edit'),
        ];
    }
}
