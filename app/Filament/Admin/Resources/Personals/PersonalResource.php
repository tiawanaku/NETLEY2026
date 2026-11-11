<?php

namespace App\Filament\Admin\Resources\Personals;

use App\Filament\Admin\Resources\Personals\Pages\CreatePersonal;
use App\Filament\Admin\Resources\Personals\Pages\EditPersonal;
use App\Filament\Admin\Resources\Personals\Pages\ListPersonals;
use App\Filament\Admin\Resources\Personals\Schemas\PersonalForm;
use App\Filament\Admin\Resources\Personals\Tables\PersonalsTable;
use App\Filament\Admin\Support\ModoMenu;
use App\Models\Personal;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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

    /** La ficha también abre a los retirados (borrado lógico), solo para verla. */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
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

    /**
     * En modo simple (ver ModoMenu), el único ítem "Personal" se reemplaza
     * por 2: Vigentes (habilitados y deshabilitados) / Retirados
     * (eliminados), cada uno apuntando a su
     * propia pestaña de ListPersonals (?tab=...). En modo completo queda el
     * único ítem de siempre.
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
            NavigationItem::make('Vigentes')
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->sort(static::getNavigationSort())
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.*') && request()->query('tab', 'vigentes') === 'vigentes')
                ->url("{$base}?tab=vigentes"),
            NavigationItem::make('Retirados')
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->sort(static::getNavigationSort() + 1)
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.*') && request()->query('tab') === 'retirados')
                ->url("{$base}?tab=retirados"),
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
