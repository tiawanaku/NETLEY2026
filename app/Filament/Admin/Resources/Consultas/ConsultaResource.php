<?php

namespace App\Filament\Admin\Resources\Consultas;

use App\Filament\Admin\Resources\Consultas\Pages\CreateConsulta;
use App\Filament\Admin\Resources\Consultas\Pages\EditConsulta;
use App\Filament\Admin\Resources\Consultas\Pages\ListConsultas;
use App\Filament\Admin\Resources\Consultas\Schemas\ConsultaForm;
use App\Filament\Admin\Resources\Consultas\Tables\ConsultasTable;
use App\Models\Consulta;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

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

    public static function getPages(): array
    {
        return [
            'index' => ListConsultas::route('/'),
            'create' => CreateConsulta::route('/create'),
            'edit' => EditConsulta::route('/{record}/edit'),
        ];
    }
}
