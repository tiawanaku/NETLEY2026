<?php

namespace App\Filament\Admin\Resources\Casos;

use App\Enums\EstadoCaso;
use Filament\Navigation\NavigationItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
use App\Filament\Admin\Resources\Casos\RelationManagers\CitasRelationManager;
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

    /**
     * En modo simple (ver ModoMenu) el ítem "Casos" se reemplaza por
     * "Casos cerrados" y "Casos eliminados" (los activos se ven desde la
     * ficha de cada cliente ejecutivo); en modo completo queda "Casos".
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        if (! ModoMenu::esSimple()) {
            return parent::getNavigationItems();
        }

        $base = static::getUrl('index');

        return [
            NavigationItem::make('Casos cerrados')
                ->group(static::getNavigationGroup())
                ->icon('heroicon-o-archive-box')
                ->sort(static::getNavigationSort() + 10)
                ->isActiveWhen(fn (): bool => static::pestanaActual() === 'cerrados')
                ->url("{$base}?tab=cerrados"),
            NavigationItem::make('Casos eliminados')
                ->group(static::getNavigationGroup())
                ->icon('heroicon-o-trash')
                ->sort(static::getNavigationSort() + 11)
                ->visible(fn (): bool => auth()->user()?->puede('eliminar') ?? false)
                ->isActiveWhen(fn (): bool => static::pestanaActual() === 'eliminados')
                ->url("{$base}?tab=eliminados"),
        ];
    }

    /**
     * Pestaña del listado de casos a la que pertenece la pantalla actual: en
     * el listado, el ?tab=; dentro de una ficha, según el caso (eliminado o
     * cerrado). Usado para marcar el ítem del menú lateral.
     */
    public static function pestanaActual(): ?string
    {
        if (! request()->routeIs(static::getRouteBaseName().'.*')) {
            return null;
        }

        if (filled($tab = request()->query('tab'))) {
            return (string) $tab;
        }

        $record = request()->route('record');
        $caso = $record instanceof Caso ? $record : (filled($record) ? Caso::withTrashed()->find($record) : null);

        return match (true) {
            $caso?->trashed() => 'eliminados',
            $caso?->estado === EstadoCaso::Cerrado => 'cerrados',
            default => null,
        };
    }

    /** La ficha de un caso eliminado también se puede abrir (para revisarlo o restaurarlo). */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
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
            RelationGroup::make('Documentos', [
                DocumentosRelationManager::class,
            ])->icon('heroicon-o-document-text'),
            RelationGroup::make('Datos judiciales', [
                SeguimientosRelationManager::class,
                FiscaliasRelationManager::class,
                JuzgadosRelationManager::class,
                OtrosSeguimientosRelationManager::class,
            ])->icon('heroicon-o-clipboard-document-list'),
            // Citas con el personal + botón para agendar una nueva.
            CitasRelationManager::class,
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
