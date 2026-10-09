<?php

namespace App\Filament\Admin\Resources\Testimonios\Schemas;

use App\Enums\EstadoAprobacion;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TestimonioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel()
            ->columns(1)
            ->components([
                TextInput::make('nombre')
                    ->required(),
                Textarea::make('testimonio')
                    ->required(),
                TextInput::make('calificacion')
                    ->required()
                    ->numeric()
                    ->default(5),
                TextInput::make('correo')
                    ->default(null),
                DateTimePicker::make('fecha')
                    ->required(),
                Select::make('estado')
                    ->options(EstadoAprobacion::class)
                    ->default('pendiente')
                    ->required(),
                Toggle::make('visible')
                    ->required(),
                Textarea::make('notas_admin')
                    ->label('Notas internas de moderación')
                    ->default(null),
            ]);
    }
}
