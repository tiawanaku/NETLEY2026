<?php

namespace App\Filament\Admin\Resources\Consultas\Schemas;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Support\CamposDomicilio;
use App\Filament\Admin\Support\CamposTelefono;
use App\Filament\Admin\Support\RespuestaFields;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ConsultaForm
{
    protected static array $origenes = [
        'Cliente antiguo',
        'Recomendación',
        'Espontáneo',
        'Página Netley',
        'Facebook',
        'WhatsApp',
        'TikTok',
        'Físico',
    ];

    /**
     * @param  array<int, \Filament\Schemas\Components\Component>  $before  Componentes a mostrar antes del formulario (ej. rótulo de N° de consulta en Crear).
     */
    public static function configure(Schema $schema, array $before = []): Schema
    {
        // La consulta queda de solo lectura una vez creada, salvo que el
        // usuario active el modo edición (botón "Editar" en EditConsulta).
        $isEditing = fn (string $operation, $livewire): bool => $operation === 'edit' && ! ($livewire->editando ?? false);

        return $schema
            ->inlineLabel()
            ->columns(2)
            ->components([
                ...$before,
                Section::make('Datos de contacto')
                    ->schema([
                        TextInput::make('nombres')->required()->maxLength(40)->regex('/^[^0-9]*$/')->disabled($isEditing)->extraInputAttributes(self::atributosSoloLetras()),
                        TextInput::make('ap_paterno')->label('Apellido paterno')->regex('/^[^0-9]*$/')->disabled($isEditing)->extraInputAttributes(self::atributosSoloLetras()),
                        TextInput::make('ap_materno')->label('Apellido materno')->regex('/^[^0-9]*$/')->disabled($isEditing)->extraInputAttributes(self::atributosSoloLetras()),
                        TextInput::make('telefono')->tel()->prefix('+591', isInline: true)->regex('/^[0-9]*$/')->maxLength(20)->disabled($isEditing)->extraInputAttributes(CamposTelefono::atributos()),
                        TextInput::make('whatsapp')->tel()->prefix('+591', isInline: true)->regex('/^[0-9]*$/')->maxLength(20)->disabled($isEditing)->extraInputAttributes(CamposTelefono::atributos()),
                        TextInput::make('correo')->email()->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                        ...CamposDomicilio::region($isEditing),
                    ]),

                Group::make([
                    Section::make('La consulta')
                        ->schema([
                            Textarea::make('consulta')->required()->rows(4)->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            Textarea::make('nota_interna')->label('Nota interna')->rows(2)->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                        ]),

                    // El switch solo tiene sentido al crear (decidir si se
                    // responde de una vez); al editar una consulta que ya
                    // tiene respuesta, el cuadro se muestra directo, sin
                    // switch, con lo que ya se guardó.
                    Toggle::make('tiene_respuesta')
                        ->label('Respuesta')
                        ->live()
                        ->visible(fn (string $operation) => $operation === 'create')
                        ->columnSpanFull(),

                    Section::make('Respuesta')
                        ->schema(RespuestaFields::schema())
                        ->visible(fn (Get $get) => (bool) $get('tiene_respuesta'))
                        ->disabled($isEditing)
                        ->columns(2),

                    Section::make('Seguimiento')
                        ->schema([
                            // Solo se pide la fecha; la hora se registra sola por dentro (ver
                            // CreateConsulta), no se le pregunta al usuario.
                            DatePicker::make('fecha_consulta')->required()->default(now())->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            Select::make('estado')
                                ->options(collect([
                                    EstadoConsulta::Pendiente,
                                    EstadoConsulta::NoContesta,
                                    EstadoConsulta::Respondido,
                                    EstadoConsulta::SoloConsulta,
                                    EstadoConsulta::RemitirPsicologia,
                                    EstadoConsulta::RemitirSocial,
                                ])->mapWithKeys(fn (EstadoConsulta $e) => [$e->value => $e->getLabel()]))
                                ->required()
                                ->disabled($isEditing)
                                ->extraInputAttributes(['data-enter-nav' => 'true']),
                            TextInput::make('origen')
                                ->required()
                                ->datalist(self::$origenes)
                                ->regex('/^[^0-9]*$/')
                                ->disabled($isEditing)
                                ->extraInputAttributes(self::atributosSoloLetras()),
                        ]),
                ]),

                Section::make('Domicilio')
                    ->schema([
                        ...CamposDomicilio::detalle($isEditing),
                        CamposDomicilio::mapa($isEditing),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(false),
            ]);
    }

    protected static function atributosSoloLetras(): array
    {
        return [
            'data-enter-nav' => 'true',
            'oninput' => 'this.value=this.value.replace(/[0-9]/g,\'\')',
            // Sin esto, Chrome detecta por heurística que "pais"/"provincia"/
            // "ciudad" son campos de dirección y ofrece su propio autocompletado
            // (direcciones guardadas en el navegador), que tapa o se mezcla con
            // las sugerencias reales de nuestro <datalist>.
            'autocomplete' => 'off',
        ];
    }
}
