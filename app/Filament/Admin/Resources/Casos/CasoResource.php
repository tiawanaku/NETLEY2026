<?php

namespace App\Filament\Admin\Resources\Casos;

use App\Enums\Rol;
use App\Filament\Admin\Concerns\ScopesToAssignedAbogado;
use App\Filament\Admin\Support\ModoMenu;
use App\Filament\Admin\Resources\Casos\Pages\CreateCaso;
use App\Filament\Admin\Resources\Casos\Pages\EditCaso;
use App\Filament\Admin\Resources\Casos\Pages\ListCasos;
use App\Filament\Admin\Resources\Casos\RelationManagers\DocumentosRelationManager;
use App\Filament\Admin\Resources\Casos\RelationManagers\FiscaliasRelationManager;
use App\Filament\Admin\Resources\Casos\RelationManagers\JuzgadosRelationManager;
use App\Filament\Admin\Resources\Casos\RelationManagers\OtrosSeguimientosRelationManager;
use App\Filament\Admin\Resources\Casos\RelationManagers\CronologiaRelationManager;
use App\Filament\Admin\Resources\Casos\RelationManagers\PlanesPagoRelationManager;
use App\Filament\Admin\Resources\Casos\RelationManagers\SeguimientosRelationManager;
use App\Filament\Admin\Resources\Casos\Schemas\CasoForm;
use App\Filament\Admin\Resources\Casos\Tables\CasosTable;
use App\Models\Caso;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationGroup;
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

    /** Oculto del menú en modo simple (ver ModoMenu); sigue accesible desde la ficha del cliente. */
    public static function shouldRegisterNavigation(): bool
    {
        return ! ModoMenu::esSimple();
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
        // Pestañas de la ficha del caso, después de "Datos del caso" (ver
        // EditCaso): cada grupo muestra sus tablas una debajo de otra.
        return [
            RelationGroup::make('Pagos', [
                // Solo el plan de pagos (con su resumen arriba): la tabla de
                // pagos sueltos se quitó a pedido; cada cuota se paga y su
                // recibo se imprime desde su fila.
                PlanesPagoRelationManager::class,
            ])->icon('heroicon-o-banknotes'),
            RelationGroup::make('Documentación', [
                DocumentosRelationManager::class,
            ])->icon('heroicon-o-document-text'),
            RelationGroup::make('Seguimiento', [
                SeguimientosRelationManager::class,
                FiscaliasRelationManager::class,
                JuzgadosRelationManager::class,
                OtrosSeguimientosRelationManager::class,
            ])->icon('heroicon-o-clipboard-document-list'),
            // Línea de tiempo con todo lo anterior junto (solo lectura).
            CronologiaRelationManager::class,
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
