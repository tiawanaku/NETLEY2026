<?php

namespace App\Filament\Admin\Resources\Personals\Schemas;

use App\Enums\EstadoPersonal;
use App\Enums\Rol;
use App\Filament\Admin\Support\CamposTelefono;
use App\Support\PersonalEspecialidades;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PersonalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // Etiqueta al lado del campo (no encima), en todo el formulario.
            ->inlineLabel()
            ->columns(2)
            ->components([
                Group::make([
                    Section::make('Datos personales')
                        ->schema([
                            TextInput::make('nombres')->required()->maxLength(60)->regex('/^[^0-9]*$/')->extraInputAttributes(self::atributosSoloLetras()),
                            TextInput::make('ap_paterno')->label('Apellido paterno')->required()->maxLength(40)->regex('/^[^0-9]*$/')->extraInputAttributes(self::atributosSoloLetras()),
                            TextInput::make('ap_materno')->label('Apellido materno')->maxLength(40)->regex('/^[^0-9]*$/')->extraInputAttributes(self::atributosSoloLetras()),
                            TextInput::make('ci')->label('Carnet de identidad')->required()->unique(ignoreRecord: true)->regex('/^[0-9]*$/')->extraInputAttributes(self::atributosSoloNumeros())->validationMessages(['unique' => 'Ya existe una persona registrada con este carnet de identidad. Revisa el dato.']),
                            Select::make('ci_expedido')
                                ->label('Expedido en')
                                ->options([
                                    'LP' => 'La Paz',
                                    'CB' => 'Cochabamba',
                                    'SC' => 'Santa Cruz',
                                    'OR' => 'Oruro',
                                    'PT' => 'Potosí',
                                    'CH' => 'Chuquisaca',
                                    'TJ' => 'Tarija',
                                    'BN' => 'Beni',
                                    'PD' => 'Pando',
                                ])
                                ->searchable()
                                // Select ->searchable() no renderiza un <select> nativo, sino un botón +
                                // panel de Alpine cargado por JS aparte; extraInputAttributes() no llega
                                // a ese botón (solo aplica al <select> nativo), así que se marca el
                                // contenedor del campo y el JS resuelve el botón real dentro de él.
                                ->extraAttributes(['data-enter-nav-field' => 'true']),
                            DatePicker::make('fecha_nacimiento')->label('Fecha de nacimiento')->required()->extraInputAttributes(['data-enter-nav' => 'true']),
                            Select::make('genero')
                                ->options(['Masculino' => 'Masculino', 'Femenino' => 'Femenino', 'Otro' => 'Otro'])
                                ->extraInputAttributes(['data-enter-nav' => 'true']),
                            TextInput::make('nacionalidad')->regex('/^[^0-9]*$/')->extraInputAttributes(self::atributosSoloLetras()),
                            Select::make('estado_civil')
                                ->options(['Soltero' => 'Soltero', 'Casado' => 'Casado', 'Divorciado' => 'Divorciado', 'Viudo' => 'Viudo'])
                                ->extraInputAttributes(['data-enter-nav' => 'true']),
                        ]),

                    Section::make('Contacto')
                        ->schema([
                            TextInput::make('telefono')->tel()->prefix('+591', isInline: true)->regex('/^[0-9]*$/')->maxLength(20)->required()->unique(ignoreRecord: true)->validationMessages(['unique' => 'Ya existe una persona registrada con este número de teléfono. Revisa el dato.'])->extraInputAttributes(CamposTelefono::atributos()),
                            TextInput::make('whatsapp')->tel()->prefix('+591', isInline: true)->regex('/^[0-9]*$/')->maxLength(20)->extraInputAttributes(CamposTelefono::atributos()),
                            TextInput::make('correo')->email()->required()->extraInputAttributes(['data-enter-nav' => 'true']),
                            TextInput::make('ciudad_residencia')->label('Ciudad de residencia')->extraInputAttributes(['data-enter-nav' => 'true']),
                            TextInput::make('direccion')->extraInputAttributes(['data-enter-nav' => 'true']),
                        ]),
                ]),

                Group::make([
                    Section::make('Foto')
                        ->schema([
                            FileUpload::make('foto')
                                ->label('')
                                ->image()
                                ->imageAspectRatio('1:1')
                                ->imagePreviewHeight('20rem')
                                ->circleCropper()
                                ->directory('personal/fotos'),
                        ]),

                    Section::make('Datos laborales')
                        ->schema([
                        Select::make('rol')
                            ->label('Rol')
                            ->options(Rol::class)
                            ->required()
                            ->extraInputAttributes(['data-enter-nav' => 'true']),
                        Select::make('estado')
                            ->options(EstadoPersonal::class)
                            ->live()
                            ->required()
                            ->extraInputAttributes(['data-enter-nav' => 'true', 'data-enter-nav-live' => 'true']),
                        Select::make('cargo')
                            ->label('Profesión')
                            ->multiple()
                            ->options(collect(PersonalEspecialidades::cargos())->mapWithKeys(fn ($v) => [$v => $v]))
                            // La columna `cargo` es un varchar comma-joined (igual que en el
                            // sistema legacy, no un JSON array); App\Casts\CsvArray la
                            // convierte de/a arreglo de forma transparente para el modelo.
                            ->live()
                            ->searchable()
                            ->extraAttributes(['data-enter-nav-field' => 'true', 'data-enter-nav-live' => 'true']),
                        // Especialidades depende de las profesiones marcadas arriba (campo
                        // "cargo"): se muestra la unión de los grupos de especialidad de
                        // todas las que se hayan marcado (Abogado→Legales, Psicologo/
                        // Psicologia→Psicología, Medico→Médicas, Trabajador Social/Trabajo
                        // Social→Trabajo Social).
                        Select::make('especialidades')
                            ->label('Especialidades')
                            ->multiple()
                            ->options(fn (Get $get) => PersonalEspecialidades::groupedForProfesiones($get('cargo')))
                            ->visible(fn (Get $get) => filled(PersonalEspecialidades::groupedForProfesiones($get('cargo'))))
                            ->helperText('Depende de la(s) profesión(es) seleccionada(s) arriba.')
                            ->searchable()
                            ->extraAttributes(['data-enter-nav-field' => 'true']),
                        Select::make('profesion')
                            ->label('Cargo')
                            ->options(PersonalEspecialidades::profesiones())
                            ->searchable()
                            ->extraAttributes(['data-enter-nav-field' => 'true']),
                        Toggle::make('tiene_contrato')->label('Tiene contrato vigente')->default(true),
                        // Solo se pide la fecha; la hora se registra sola por dentro (ver
                        // CreatePersonal/EditPersonal), no se le pregunta al usuario.
                        DatePicker::make('fecha_inicio')->label('Fecha de inicio')->default(now())->extraInputAttributes(['data-enter-nav' => 'true']),
                        DatePicker::make('fecha_fin')
                            ->label('Fecha de baja')
                            ->visible(fn (Get $get) => $get('estado') === EstadoPersonal::Inhabilitado->value),
                        Textarea::make('motivo_baja')
                            ->label('Motivo de baja')
                            ->visible(fn (Get $get) => $get('estado') === EstadoPersonal::Inhabilitado->value)
                            ->extraInputAttributes(['data-enter-nav' => 'true']),
                        Textarea::make('nota')
                            ->label('Nota interna')
                            ->extraInputAttributes(['data-enter-nav' => 'true']),
                    ]),
                ]),
            ]);
    }

    protected static function atributosSoloLetras(): array
    {
        return [
            'data-enter-nav' => 'true',
            'oninput' => 'this.value=this.value.replace(/[0-9]/g,\'\')',
        ];
    }

    protected static function atributosSoloNumeros(): array
    {
        return [
            'data-enter-nav' => 'true',
            'inputmode' => 'numeric',
            'oninput' => 'this.value=this.value.replace(/[^0-9]/g,\'\')',
        ];
    }
}
