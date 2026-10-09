<?php

namespace App\Filament\Admin\Resources\Tallers\Schemas;

use App\Filament\Admin\Forms\Components\DelitoSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TallerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel()
            ->components([
                Section::make('Datos del contacto')
                    ->schema([
                        TextInput::make('nombres')->required(),
                        TextInput::make('ap_paterno')->label('Apellido paterno'),
                        TextInput::make('ap_materno')->label('Apellido materno'),
                        TextInput::make('telefono')->tel(),
                        TextInput::make('whatsapp')->tel(),
                        TextInput::make('ciudad'),
                        TextInput::make('numero_consulta')->label('N° de consulta'),
                        TextInput::make('origen')->helperText('Ej. "taller colegio: San Calixto"'),
                    ]),

                Section::make('Taller / consulta')
                    ->schema([
                        DatePicker::make('fecha_programada'),
                        TimePicker::make('hora_programada'),
                        ...DelitoSelect::make(areaField: 'materia_legal', delitoField: 'delito_id', required: false),
                        Textarea::make('motivo'),
                        Textarea::make('consulta'),
                        Textarea::make('respuesta'),
                    ]),
            ]);
    }
}
