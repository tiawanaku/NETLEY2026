<?php

namespace App\Filament\Admin\Resources\Contactos\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel()
            ->columns(1)
            ->components([
                Select::make('personal')
                    ->label('Personal vinculado')
                    ->relationship('personal', 'nombres')
                    ->multiple()
                    ->searchable(),
                TextInput::make('institucion')
                    ->default(null),
                TextInput::make('entidad')
                    ->default(null),
                TextInput::make('unidad')
                    ->default(null),
                TextInput::make('cargo')
                    ->default(null),
                TextInput::make('profesion')
                    ->default(null),
                TextInput::make('nombre')
                    ->required(),
                TextInput::make('apellido')
                    ->default(null),
                TextInput::make('direccion')
                    ->default(null),
                TextInput::make('zona')
                    ->default(null),
                TextInput::make('ciudad')
                    ->default(null),
                TextInput::make('correo')
                    ->default(null),
                TextInput::make('telefono')
                    ->tel()
                    ->default(null),
                TextInput::make('horario_contacto')
                    ->default(null),
                Textarea::make('nota')
                    ->default(null),
            ]);
    }
}
