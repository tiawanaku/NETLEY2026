<?php

namespace App\Filament\Admin\Resources\Casos;

use App\Enums\Rol;
use App\Filament\Admin\Concerns\ScopesToAssignedAbogado;
use App\Filament\Admin\Resources\Casos\Pages\CreateCaso;
use App\Filament\Admin\Resources\Casos\Pages\EditCaso;
use App\Filament\Admin\Resources\Casos\Pages\ListCasos;
use App\Filament\Admin\Resources\Casos\RelationManagers\DocumentosRelationManager;
use App\Filament\Admin\Resources\Casos\RelationManagers\FiscaliasRelationManager;
use App\Filament\Admin\Resources\Casos\RelationManagers\JuzgadosRelationManager;
use App\Filament\Admin\Resources\Casos\RelationManagers\OtrosSeguimientosRelationManager;
use App\Filament\Admin\Resources\Casos\RelationManagers\PagosRelationManager;
use App\Filament\Admin\Resources\Casos\RelationManagers\PlanesPagoRelationManager;
use App\Filament\Admin\Resources\Casos\RelationManagers\SeguimientosRelationManager;
use App\Filament\Admin\Resources\Casos\Schemas\CasoForm;
use App\Filament\Admin\Resources\Casos\Tables\CasosTable;
use App\Models\Caso;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CasoResource extends Resource
{
    use ScopesToAssignedAbogado;

    protected static ?string $model = Caso::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Casos';

    protected static \UnitEnum|string|null $navigationGroup = 'Cliente Ejecutivo';

    protected static ?int $navigationSort = 31;

    protected static ?string $recordTitleAttribute = 'id';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('casos') ?? false;
    }

    /** Pasantes y procuradoras no pueden dar de alta procesos nuevos. */
    public static function canCreate(): bool
    {
        return ! (auth()->user()?->hasRole(Rol::Pasante, Rol::Procurador) ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return CasoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CasosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PlanesPagoRelationManager::class,
            PagosRelationManager::class,
            DocumentosRelationManager::class,
            FiscaliasRelationManager::class,
            JuzgadosRelationManager::class,
            OtrosSeguimientosRelationManager::class,
            SeguimientosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCasos::route('/'),
            'create' => CreateCaso::route('/create'),
            'edit' => EditCaso::route('/{record}/edit'),
        ];
    }
}
