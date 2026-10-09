<?php

namespace App\Filament\Admin\Resources\Citas;

use App\Filament\Admin\Concerns\ScopesToAssignedAbogado;
use App\Filament\Admin\Resources\Citas\Pages\CreateCita;
use App\Filament\Admin\Resources\Citas\Pages\EditCita;
use App\Filament\Admin\Resources\Citas\Pages\ListCitas;
use App\Filament\Admin\Resources\Citas\RelationManagers\InformeRelationManager;
use App\Filament\Admin\Resources\Citas\Schemas\CitaForm;
use App\Filament\Admin\Resources\Citas\Tables\CitasTable;
use App\Models\Cita;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CitaResource extends Resource
{
    use ScopesToAssignedAbogado;

    protected static ?string $model = Cita::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Agenda';

    protected static \UnitEnum|string|null $navigationGroup = 'Agenda';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'agenda';

    protected static ?string $recordTitleAttribute = 'detalle';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('agenda') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return CitaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CitasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            InformeRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCitas::route('/'),
            'create' => CreateCita::route('/create'),
            'edit' => EditCita::route('/{record}/edit'),
        ];
    }
}
