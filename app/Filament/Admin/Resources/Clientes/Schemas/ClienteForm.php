<?php

namespace App\Filament\Admin\Resources\Clientes\Schemas;

use Afsakar\LeafletMapPicker\LeafletMapPicker;
use App\Filament\Admin\Support\ClienteCasoWizard;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClienteForm
{
    public static function configure(Schema $schema): Schema
    {
        // Igual que en Consultas: la ficha del cliente queda de solo lectura
        // una vez creada — /clientes/{id}/edit sirve para CONSULTAR el
        // registro, no para reescribirlo.
        $isEditing = fn (string $operation): bool => $operation === 'edit';

        return $schema
            ->inlineLabel()
            ->components([
                Section::make('Datos del cliente')
                    ->schema([
                        TextInput::make('nombres')->required()->maxLength(60)->disabled($isEditing),
                        TextInput::make('ap_paterno')->label('Apellido paterno')->required()->maxLength(30)->disabled($isEditing),
                        TextInput::make('ap_materno')->label('Apellido materno')->maxLength(30)->disabled($isEditing),
                        TextInput::make('ci')->label('Carnet de identidad')->required()->unique(ignoreRecord: true)->disabled($isEditing),
                        TextInput::make('extension')->label('Extensión CI')->maxLength(20)->disabled($isEditing),
                        Select::make('sucursal')
                            ->options(['LA PAZ' => 'La Paz', 'SANTA CRUZ' => 'Santa Cruz', 'COCHABAMBA' => 'Cochabamba'])
                            ->default('LA PAZ')
                            ->required()
                            ->disabled($isEditing),
                    ]),

                Section::make('Contacto')
                    ->schema([
                        TextInput::make('telefono')->tel()->disabled($isEditing),
                        TextInput::make('whatsapp')->tel()->disabled($isEditing),
                        TextInput::make('correo')->email()->disabled($isEditing),
                        TextInput::make('direccion')->disabled($isEditing),
                    ]),

                Section::make('Ubicación del domicilio')
                    ->schema([
                        TextInput::make('zona')->label('Zona / barrio')->disabled($isEditing),
                        TextInput::make('calles')->label('Calle(s)')->disabled($isEditing),
                        TextInput::make('numero_domicilio')->label('N° casa / depto.')->disabled($isEditing),
                        Textarea::make('indicaciones_domicilio')->label('Indicaciones')->rows(2)->disabled($isEditing),
                        LeafletMapPicker::make('ubicacion')
                            ->hiddenLabel()
                            ->defaultLocation(ClienteCasoWizard::CENTRO_MAPA)
                            ->defaultZoom(16)
                            ->height('320px')
                            ->customMarker(ClienteCasoWizard::marcadorMapa())
                            ->readOnly(),
                    ]),
            ]);
    }
}
