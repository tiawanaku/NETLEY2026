<?php

namespace App\Filament\Admin\Resources\Llamadas\Schemas;

use App\Filament\Admin\Forms\Components\DelitoSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LlamadaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel()
            ->components([
                Section::make('Contacto')
                    ->schema([
                        TextInput::make('nombres')->required(),
                        TextInput::make('ap_paterno')->label('Apellido paterno'),
                        TextInput::make('ap_materno')->label('Apellido materno'),
                        TextInput::make('telefono')->tel(),
                        TextInput::make('whatsapp')->tel(),
                        TextInput::make('ciudad'),
                        TextInput::make('numero_consulta')->label('N° de consulta'),
                        TextInput::make('origen'),
                    ]),

                Section::make('Motivo de la llamada')
                    ->schema([
                        ...DelitoSelect::make(areaField: 'materia_legal', delitoField: 'delito_id', required: false),
                        Textarea::make('motivo'),
                        Textarea::make('observaciones'),
                        Textarea::make('observaciones_netley')->label('Nota interna'),
                    ]),

                Section::make('Asignación y agenda')
                    ->schema([
                        Select::make('personal_id')
                            ->label('Personal asignado')
                            ->relationship('personal', 'nombres')
                            ->searchable(),
                        Select::make('consulta_id')
                            ->label('Consulta relacionada')
                            ->relationship('consulta', 'nombres')
                            ->searchable(),
                        TextInput::make('accion'),
                        DatePicker::make('fecha'),
                        TimePicker::make('hora'),
                        TimePicker::make('duracion'),
                    ]),

                Section::make('Datos económicos')
                    ->schema([
                        TextInput::make('iguala')->numeric()->prefix('Bs.')->default(0),
                        TextInput::make('anticipo')->numeric()->prefix('Bs.')->default(0),
                        TextInput::make('comision_bs')->label('Comisión (Bs.)')->numeric()->default(0),
                        TextInput::make('comision_pct')->label('Comisión (%)')->numeric()->default(0),
                    ]),
            ]);
    }
}
