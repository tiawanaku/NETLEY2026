<?php

namespace App\Filament\Admin\Resources\VideosPortada;

use App\Filament\Admin\Resources\VideosPortada\Pages\ManageVideosPortada;
use App\Models\VideoPortada;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/** Videos que se muestran en la sección "Conócenos" de la frontpage. */
class VideoPortadaResource extends Resource
{
    protected static ?string $model = VideoPortada::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static ?string $navigationLabel = 'Videos';

    protected static ?string $slug = 'videos';

    protected static \UnitEnum|string|null $navigationGroup = 'Sitio web';

    protected static ?int $navigationSort = 101;

    protected static ?string $recordTitleAttribute = 'titulo';

    protected static ?string $modelLabel = 'video';

    protected static ?string $pluralModelLabel = 'videos';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('sitio_web') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('titulo')->required()->maxLength(150),
                Textarea::make('descripcion')->maxLength(1000),
                TextInput::make('url')
                    ->label('Enlace de YouTube o Vimeo')
                    ->url()
                    ->helperText('Pega el enlace del video (youtube.com/watch?v=..., youtu.be/... o vimeo.com/...). Si prefieres subir el archivo, déjalo vacío.'),
                FileUpload::make('archivo')
                    ->label('Archivo de video (alternativa a la URL)')
                    ->disk('public')
                    ->directory('frontpage/videos')
                    ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime']),
                TextInput::make('orden')->numeric()->default(0),
                Toggle::make('activo')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')->searchable(),
                TextColumn::make('url')->limit(40)->label('Fuente'),
                IconColumn::make('archivo')->boolean()->label('Archivo subido')->getStateUsing(fn (VideoPortada $record) => filled($record->archivo)),
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
            'index' => ManageVideosPortada::route('/'),
        ];
    }
}
