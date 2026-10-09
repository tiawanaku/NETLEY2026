<?php

namespace App\Filament\Admin\Resources\RedesSociales;

use App\Enums\RedSocialPlataforma;
use App\Filament\Admin\Resources\RedesSociales\Pages\ManageRedesSociales;
use App\Models\RedSocial;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/** Enlaces a redes sociales mostrados en el pie de página de la frontpage. */
class RedSocialResource extends Resource
{
    protected static ?string $model = RedSocial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static ?string $navigationLabel = 'Redes sociales';

    protected static ?string $slug = 'redes-sociales';

    protected static \UnitEnum|string|null $navigationGroup = 'Sitio web';

    protected static ?int $navigationSort = 102;

    protected static ?string $recordTitleAttribute = 'plataforma';

    protected static ?string $modelLabel = 'red social';

    protected static ?string $pluralModelLabel = 'redes sociales';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('sitio_web') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('plataforma')
                    ->options(RedSocialPlataforma::class)
                    ->required(),
                TextInput::make('url')
                    ->label('Enlace')
                    ->url()
                    ->required(),
                TextInput::make('orden')->numeric()->default(0),
                Toggle::make('activo')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plataforma')->badge(),
                TextColumn::make('url')->limit(50),
                ToggleColumn::make('activo'),
            ])
            ->defaultSort('orden')
            ->reorderable('orden')
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                EditAction::make()->visible(fn () => auth()->user()?->puede('editar')),
                DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRedesSociales::route('/'),
        ];
    }
}
