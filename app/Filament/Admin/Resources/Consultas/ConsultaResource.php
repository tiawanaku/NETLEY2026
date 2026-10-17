<?php

namespace App\Filament\Admin\Resources\Consultas;

use App\Filament\Admin\Resources\Consultas\Pages\CreateConsulta;
use App\Filament\Admin\Resources\Consultas\Pages\EditConsulta;
use App\Filament\Admin\Resources\Consultas\Pages\ListConsultas;
use App\Filament\Admin\Resources\Consultas\Schemas\ConsultaForm;
use App\Filament\Admin\Resources\Consultas\Tables\ConsultasTable;
use App\Filament\Admin\Support\ModoMenu;
use App\Models\Consulta;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ConsultaResource extends Resource
{
    protected static ?string $model = Consulta::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Consultas';

    protected static \UnitEnum|string|null $navigationGroup = 'Consultas';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'nombres';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('consultas') ?? false;
    }

    /** La migaja de pan ("Consultas > ...") muestra el N° de consulta, no el nombre. */
    public static function getRecordTitle(?Model $record): string|null
    {
        return $record ? 'N° '.$record->getKey() : null;
    }

    public static function form(Schema $schema): Schema
    {
        return ConsultaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConsultasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // RespuestasRelationManager::class, // oculto por el momento (a pedido del usuario)
        ];
    }

    /**
     * En modo simple (ver ModoMenu), el único ítem "Consultas" se reemplaza
     * por 3: Nuevas consultas / Agendados / Consultas cerradas, cada uno
     * apuntando a su propia pestaña de ListConsultas (?tab=...). En modo
     * completo queda el único ítem de siempre.
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
            NavigationItem::make('Nuevas consultas')
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->sort(static::getNavigationSort())
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.*') && request()->query('tab', 'nuevas') === 'nuevas')
                ->url("{$base}?tab=nuevas"),
            NavigationItem::make('Agendados')
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->sort(static::getNavigationSort() + 1)
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.*') && request()->query('tab') === 'agendados')
                ->url("{$base}?tab=agendados"),
            NavigationItem::make('Consultas cerradas')
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->sort(static::getNavigationSort() + 2)
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.*') && request()->query('tab') === 'cerradas')
                ->url("{$base}?tab=cerradas"),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsultas::route('/'),
            'create' => CreateConsulta::route('/create'),
            'edit' => EditConsulta::route('/{record}/edit'),
        ];
    }
}
