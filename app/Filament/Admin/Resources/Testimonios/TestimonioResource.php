<?php

namespace App\Filament\Admin\Resources\Testimonios;

use App\Filament\Admin\Resources\Testimonios\Pages\CreateTestimonio;
use App\Filament\Admin\Resources\Testimonios\Pages\EditTestimonio;
use App\Filament\Admin\Resources\Testimonios\Pages\ListTestimonios;
use App\Filament\Admin\Resources\Testimonios\Schemas\TestimonioForm;
use App\Filament\Admin\Resources\Testimonios\Tables\TestimoniosTable;
use App\Models\Testimonio;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TestimonioResource extends Resource
{
    protected static ?string $model = Testimonio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'Testimonios';

    protected static \UnitEnum|string|null $navigationGroup = 'Contenido público';

    protected static ?int $navigationSort = 90;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('testimonios') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return TestimonioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestimoniosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTestimonios::route('/'),
            'create' => CreateTestimonio::route('/create'),
            'edit' => EditTestimonio::route('/{record}/edit'),
        ];
    }
}
