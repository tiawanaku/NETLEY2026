<?php

namespace App\Filament\Admin\Resources\Citas\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class CitaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel()
            ->columns(1)
            ->components([
                Select::make('consulta_id')
                    ->label('Consulta relacionada')
                    ->relationship('consulta', 'nombres')
                    ->searchable(),
                Select::make('caso_id')
                    ->label('Caso relacionado')
                    ->relationship('caso', 'id')
                    ->searchable(),
                Select::make('personal')
                    ->label('Abogado(s) asignado(s)')
                    ->relationship('personal', 'nombres')
                    ->multiple()
                    ->searchable(),
                DatePicker::make('fecha')->required()->default(now()),
                TimePicker::make('hora')->required(),
                TextInput::make('tipo'),
                TextInput::make('origen'),
                Textarea::make('detalle'),
            ]);
    }
}
