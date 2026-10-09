<?php

namespace App\Filament\Admin\Resources\Delitos\Schemas;

use App\Enums\Especialidad;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DelitoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel()
            ->columns(1)
            ->components([
                Select::make('area')
                    ->label('Área / Materia legal')
                    ->options(fn () => collect(Especialidad::cases())->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()]))
                    ->native(false)
                    ->searchable()
                    ->required(),
                TextInput::make('delito')
                    ->label('Delito / Materia específica')
                    ->required()
                    ->maxLength(150),
            ]);
    }
}
