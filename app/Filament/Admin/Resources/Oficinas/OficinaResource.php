<?php

namespace App\Filament\Admin\Resources\Oficinas;

use App\Filament\Admin\Resources\Oficinas\Pages\ManageOficinas;
use App\Models\Oficina;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OficinaResource extends Resource
{
    protected static ?string $model = Oficina::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Oficinas';

    protected static \UnitEnum|string|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 82;

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('oficinas') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel()
            ->columns(1)
            ->components([
                TextInput::make('ciudad')
                    ->required(),
                TextInput::make('oficina')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ciudad')
                    ->searchable(),
                TextColumn::make('oficina')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
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
            'index' => ManageOficinas::route('/'),
        ];
    }
}
