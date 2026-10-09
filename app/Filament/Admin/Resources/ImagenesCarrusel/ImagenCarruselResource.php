<?php

namespace App\Filament\Admin\Resources\ImagenesCarrusel;

use App\Filament\Admin\Resources\ImagenesCarrusel\Pages\ManageImagenesCarrusel;
use App\Models\ImagenCarrusel;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/** Imágenes del carrusel principal de la frontpage (resources/views/frontpage/index.blade.php). */
class ImagenCarruselResource extends Resource
{
    protected static ?string $model = ImagenCarrusel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Carrusel de imágenes';

    protected static ?string $slug = 'carrusel-imagenes';

    protected static \UnitEnum|string|null $navigationGroup = 'Sitio web';

    protected static ?int $navigationSort = 100;

    protected static ?string $recordTitleAttribute = 'titulo';

    protected static ?string $modelLabel = 'imagen';

    protected static ?string $pluralModelLabel = 'imágenes del carrusel';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('sitio_web') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                FileUpload::make('imagen')
                    ->label('Imagen')
                    ->image()
                    ->disk('public')
                    ->directory('frontpage/carrusel')
                    ->imageEditor()
                    ->required(),
                TextInput::make('titulo')->maxLength(150),
                TextInput::make('subtitulo')->maxLength(250),
                TextInput::make('enlace')
                    ->label('Enlace (opcional)')
                    ->url()
                    ->helperText('A dónde lleva el botón de esta diapositiva, por ejemplo un enlace a #consulta.'),
                TextInput::make('orden')->numeric()->default(0),
                Toggle::make('activo')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('imagen')->disk('public'),
                TextColumn::make('titulo')->searchable(),
                TextColumn::make('subtitulo')->limit(40),
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
            'index' => ManageImagenesCarrusel::route('/'),
        ];
    }
}
