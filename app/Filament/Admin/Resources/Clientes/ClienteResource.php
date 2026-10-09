<?php

namespace App\Filament\Admin\Resources\Clientes;

use App\Enums\Rol;
use App\Filament\Admin\Resources\Clientes\Pages\CreateCliente;
use App\Filament\Admin\Resources\Clientes\Pages\EditCliente;
use App\Filament\Admin\Resources\Clientes\Pages\ListClientes;
use App\Filament\Admin\Resources\Clientes\RelationManagers\CasosRelationManager;
use App\Filament\Admin\Resources\Clientes\RelationManagers\DocumentosRelationManager;
use App\Filament\Admin\Resources\Clientes\RelationManagers\PagosRelationManager;
use App\Filament\Admin\Resources\Clientes\Schemas\ClienteForm;
use App\Filament\Admin\Resources\Clientes\Tables\ClientesTable;
use App\Models\Cliente;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Clientes';

    protected static \UnitEnum|string|null $navigationGroup = 'Cliente Ejecutivo';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'nombres';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('clientes') ?? false;
    }

    /** Pasantes y procuradoras no pueden dar de alta clientes nuevos. */
    public static function canCreate(): bool
    {
        return ! (auth()->user()?->hasRole(Rol::Pasante, Rol::Procurador) ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return ClienteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CasosRelationManager::class,
            PagosRelationManager::class,
            DocumentosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientes::route('/'),
            'create' => CreateCliente::route('/create'),
            'edit' => EditCliente::route('/{record}/edit'),
        ];
    }
}
