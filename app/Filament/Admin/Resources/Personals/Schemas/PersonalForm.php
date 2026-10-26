<?php

namespace App\Filament\Admin\Resources\Personals\Schemas;

use App\Enums\EstadoPersonal;
use App\Enums\RedSocialPlataforma;
use App\Enums\Rol;
use App\Filament\Admin\Support\CampoConOtro;
use App\Filament\Admin\Support\CamposTelefono;
use App\Filament\Admin\Support\ReglasContacto;
use App\Models\Personal;
use App\Support\PersonalEspecialidades;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn as RepeaterTableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PersonalForm
{
    public static function configure(Schema $schema): Schema
    {
        // La ficha queda de solo lectura una vez creada, salvo que el usuario
        // active el modo edición (botón "Editar" en EditPersonal).
        $isEditing = fn (string $operation, $livewire): bool => $operation === 'edit' && ! ($livewire->editando ?? false);
        $puedeEditar = fn (string $operation, $livewire): bool => ! $isEditing($operation, $livewire);

        return $schema
            // Etiqueta al lado del campo (no encima), en todo el formulario.
            ->inlineLabel()
            ->columns(2)
            ->components([
                Group::make([
                    Section::make('Datos personales')
                        ->schema([
                            TextInput::make('nombres')->required()->maxLength(60)->regex('/^[^0-9]*$/')->disabled($isEditing)->extraInputAttributes(self::atributosSoloLetras()),
                            TextInput::make('ap_paterno')->label('Apellido paterno')->required()->maxLength(40)->regex('/^[^0-9]*$/')->disabled($isEditing)->extraInputAttributes(self::atributosSoloLetras()),
                            TextInput::make('ap_materno')->label('Apellido materno')->maxLength(40)->regex('/^[^0-9]*$/')->disabled($isEditing)->extraInputAttributes(self::atributosSoloLetras()),
                            TextInput::make('ci')->label('Carnet de identidad')->required()->regex('/^[0-9]*$/')
                                // scopedUnique() (no unique()): así el mensaje
                                // personalizado también se muestra al validar en
                                // tiempo real (->unique() solo lo toma en cuenta
                                // al guardar el formulario completo).
                                ->scopedUnique(model: Personal::class, ignoreRecord: true)
                                ->validationMessages(['unique' => 'Ya existe una persona registrada con este carnet de identidad. Revisa el dato.'])
                                // Valida apenas se completa el campo (al perder el foco), no
                                // recién al guardar.
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (TextInput $component) => $component->getLivewire()->validateOnly($component->getStatePath()))
                                ->disabled($isEditing)
                                ->extraInputAttributes(self::atributosSoloNumeros()),
                            ...array_map(
                                fn ($campo) => $campo->disabled($isEditing),
                                CampoConOtro::simple(
                                    Select::make('ci_expedido')
                                        ->label('Expedido en')
                                        ->searchable()
                                        // Select ->searchable() no renderiza un <select> nativo, sino un botón +
                                        // panel de Alpine cargado por JS aparte; extraInputAttributes() no llega
                                        // a ese botón (solo aplica al <select> nativo), así que se marca el
                                        // contenedor del campo y el JS resuelve el botón real dentro de él.
                                        ->extraAttributes(['data-enter-nav-field' => 'true', 'data-enter-nav-live' => 'true']),
                                    opciones: [
                                        'LP' => 'La Paz',
                                        'CB' => 'Cochabamba',
                                        'SC' => 'Santa Cruz',
                                        'OR' => 'Oruro',
                                        'PT' => 'Potosí',
                                        'CH' => 'Chuquisaca',
                                        'TJ' => 'Tarija',
                                        'BN' => 'Beni',
                                        'PD' => 'Pando',
                                        'OTROS' => 'Otros',
                                    ],
                                    valorOtro: 'OTROS',
                                    etiquetaTexto: 'Expedido en (especificar)',
                                    maxLength: 60,
                                ),
                            ),
                            DatePicker::make('fecha_nacimiento')->label('Fecha de nacimiento')->required()->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            Select::make('genero')
                                ->options(['Masculino' => 'Masculino', 'Femenino' => 'Femenino', 'Otro' => 'Otro'])
                                ->disabled($isEditing)
                                ->extraInputAttributes(['data-enter-nav' => 'true']),
                            TextInput::make('nacionalidad')->regex('/^[^0-9]*$/')->disabled($isEditing)->extraInputAttributes(self::atributosSoloLetras()),
                            Select::make('estado_civil')
                                ->options(['Soltero' => 'Soltero', 'Casado' => 'Casado', 'Divorciado' => 'Divorciado', 'Viudo' => 'Viudo'])
                                ->disabled($isEditing)
                                ->extraInputAttributes(['data-enter-nav' => 'true']),
                        ]),

                    Section::make('Contacto')
                        ->schema([
                            // Teléfono, WhatsApp y correo no se pueden repetir con
                            // OTRO personal (ver App\Rules\ContactoUnico).
                            CamposTelefono::make('telefono', requerido: true, deshabilitado: $isEditing, unicoEn: Personal::class, entidadUnica: 'otro personal'),
                            CamposTelefono::make('whatsapp', deshabilitado: $isEditing, unicoEn: Personal::class, entidadUnica: 'otro personal'),
                            TextInput::make('correo')
                                ->email()
                                ->required()
                                ->rules([ReglasContacto::correo(Personal::class, 'otro personal')])
                                // Valida apenas se completa el campo (al perder el foco), no
                                // recién al guardar.
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (TextInput $component) => $component->getLivewire()->validateOnly($component->getStatePath()))
                                ->disabled($isEditing)
                                ->extraInputAttributes(['data-enter-nav' => 'true']),
                            TextInput::make('ciudad_residencia')->label('Ciudad de residencia')->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            TextInput::make('direccion')->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            Repeater::make('redes_sociales')
                                ->label('Redes sociales')
                                ->table([
                                    RepeaterTableColumn::make('Red social'),
                                    RepeaterTableColumn::make('Link o usuario'),
                                ])
                                ->schema([
                                    Select::make('plataforma')
                                        ->options(
                                            collect(RedSocialPlataforma::cases())
                                                ->reject(fn (RedSocialPlataforma $caso) => $caso === RedSocialPlataforma::WhatsApp)
                                                ->mapWithKeys(fn (RedSocialPlataforma $caso) => [$caso->value => $caso->getLabel()])
                                        )
                                        ->native(false)
                                        ->disabled($isEditing)
                                        ->required(),
                                    TextInput::make('usuario')
                                        ->placeholder('Link o nombre de usuario')
                                        ->maxLength(150)
                                        ->disabled($isEditing)
                                        ->required(),
                                ])
                                ->addActionLabel('Añadir red social')
                                ->addable($puedeEditar)
                                ->deletable($puedeEditar)
                                ->reorderable(false)
                                ->defaultItems(0)
                                ->columnSpanFull(),
                        ]),
                ]),

                Group::make([
                    Section::make('Datos laborales')
                        ->schema([
                        // `rol` define los permisos de acceso al sistema (User::hasRole),
                        // por eso se sigue pidiendo, pero ya no se rotula "Cargo".
                        Select::make('rol')
                            ->label('Rol de acceso')
                            ->options(Rol::class)
                            ->required()
                            ->disabled($isEditing)
                            ->extraInputAttributes(['data-enter-nav' => 'true']),
                        Select::make('estado')
                            ->options(EstadoPersonal::class)
                            ->live()
                            ->required()
                            ->disabled($isEditing)
                            ->extraInputAttributes(['data-enter-nav' => 'true', 'data-enter-nav-live' => 'true']),
                        // La columna `cargo` es un varchar comma-joined (igual que en el
                        // sistema legacy, no un JSON array); App\Casts\CsvArray la
                        // convierte de/a arreglo de forma transparente para el modelo.
                        ...array_map(
                            fn ($campo) => $campo->disabled($isEditing),
                            CampoConOtro::multiple(
                                Select::make('cargo')
                                    ->label('Profesión')
                                    ->multiple()
                                    ->searchable()
                                    ->extraAttributes(['data-enter-nav-field' => 'true', 'data-enter-nav-live' => 'true']),
                                opciones: collect(PersonalEspecialidades::cargos())->mapWithKeys(fn ($v) => [$v => $v])->all(),
                                valorOtro: 'Otros',
                                etiquetaTexto: 'Profesión (especificar)',
                                maxLength: 150,
                            ),
                        ),
                        // Especialidades depende de las profesiones marcadas arriba (campo
                        // "cargo"): se muestra la unión de los grupos de especialidad de
                        // todas las que se hayan marcado (Abogado→Legales, Psicologo/
                        // Psicologia→Psicología, Medico→Médicas, Trabajador Social/Trabajo
                        // Social→Trabajo Social).
                        Select::make('especialidades')
                            ->label('Especialidades')
                            ->multiple()
                            ->options(fn (Get $get) => collect(PersonalEspecialidades::groupedForProfesiones($get('cargo')))->collapse()->all())
                            ->visible(fn (Get $get) => filled(PersonalEspecialidades::groupedForProfesiones($get('cargo'))))
                            ->helperText('Depende de la(s) profesión(es) seleccionada(s) arriba.')
                            ->searchable()
                            ->disabled($isEditing)
                            ->extraAttributes(['data-enter-nav-field' => 'true']),
                        // "Cargo principal" (`profesion`) y "Tiene contrato vigente"
                        // (`tiene_contrato`) no se piden en el formulario: `profesion`
                        // se deriva de "Profesión" al guardar (ver
                        // PersonalEspecialidades::profesionPrincipal()) y
                        // `tiene_contrato` queda con el default de la BD / su valor.
                        // Solo se pide la fecha; la hora se registra sola por dentro (ver
                        // CreatePersonal/EditPersonal), no se le pregunta al usuario.
                        DatePicker::make('fecha_inicio')->label('Fecha de inicio')->default(now())->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                        DatePicker::make('fecha_fin')->label('Fecha de baja')->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                        Textarea::make('motivo_baja')
                            ->label('Motivo de baja')
                            ->visible(fn (Get $get) => $get('estado') === EstadoPersonal::Inhabilitado->value)
                            ->disabled($isEditing)
                            ->extraInputAttributes(['data-enter-nav' => 'true']),
                        Textarea::make('nota')
                            ->label('Nota interna')
                            ->disabled($isEditing)
                            ->extraInputAttributes(['data-enter-nav' => 'true']),
                    ]),

                    Section::make('Documentos')
                        ->schema([
                            FileUpload::make('foto')
                                ->label('')
                                ->image()
                                ->imageAspectRatio('1:1')
                                ->imagePreviewHeight('20rem')
                                ->circleCropper()
                                ->disabled($isEditing)
                                ->directory('personal/fotos'),
                            Repeater::make('documentos')
                                ->relationship()
                                ->label('Documentos')
                                ->schema([
                                    FileUpload::make('ruta')
                                        ->label('Archivo')
                                        ->directory('documentos-personal')
                                        ->disabled($isEditing)
                                        ->required(),
                                    TextInput::make('nombre')
                                        ->label('Nombre')
                                        ->maxLength(100)
                                        ->disabled($isEditing)
                                        ->required(),
                                ])
                                ->addActionLabel('Subir documento')
                                ->addable($puedeEditar)
                                ->deletable($puedeEditar)
                                ->reorderable(false)
                                ->defaultItems(0)
                                ->columnSpanFull(),
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
