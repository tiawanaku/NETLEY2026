<?php

namespace App\Filament\Admin\Resources\Consultas\Schemas;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Support\CamposDenuncia;
use App\Filament\Admin\Support\CamposDomicilio;
use App\Filament\Admin\Support\CamposOrigen;
use App\Filament\Admin\Support\CamposTelefono;
use App\Filament\Admin\Support\ReglasContacto;
use App\Filament\Admin\Support\RespuestaFields;
use App\Models\Consulta;
use App\Support\Origenes;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ConsultaForm
{
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
                        // No repetir con OTRA consulta (ver App\Rules\ContactoUnico).
                        CamposTelefono::make('telefono', deshabilitado: $isEditing, unicoEn: Consulta::class, entidadUnica: 'otra consulta'),
                        CamposTelefono::make('whatsapp', deshabilitado: $isEditing, unicoEn: Consulta::class, entidadUnica: 'otra consulta'),
                        TextInput::make('correo')->email()->disabled($isEditing)->rules([ReglasContacto::correo(Consulta::class, 'otra consulta')])->extraInputAttributes(['data-enter-nav' => 'true']),
                        ...CamposDomicilio::region($isEditing),
                    ]),

                Group::make([
                    // Al crear la consulta, en esta columna solo se pide la
                    // materia legal, el tipo de proceso y la nota interna —
                    // el resto (texto de la consulta, respuesta, seguimiento)
                    // se completa después, desde la ficha ya creada.
                    Section::make('Consulta')
                        ->schema([
                            ...RespuestaFields::camposMateria(etiquetaDelito: 'Tipo de proceso'),
                            Textarea::make('nota_interna')->label('Nota interna')->rows(2)->columnSpanFull()->extraInputAttributes(['data-enter-nav' => 'true']),
                        ])
                        ->columns(2)
                        ->visibleOn('create'),

                    Section::make('La consulta')
                        ->schema([
                            Textarea::make('consulta')->required()->rows(4)->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            Textarea::make('nota_interna')->label('Nota interna')->rows(2)->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            // Se cargan con la acción "Agendar"; en la ficha
                            // se ven (y se editan con "Editar"). No en Crear.
                            CamposDenuncia::denunciado()->disabled($isEditing)->visibleOn('edit'),
                            CamposDenuncia::denuncias(conRelacion: true)->disabled($isEditing)->visibleOn('edit'),
                        ])
                        ->hiddenOn('create'),

                    // El switch solo tiene sentido al crear (decidir si se
                    // responde de una vez); ya no se ofrece en Crear (ver
                    // Section "Consulta" arriba), así que queda oculto
                    // siempre — solo sirve como contenedor del dato al editar
                    // una consulta que ya tiene respuesta guardada.
                    Toggle::make('tiene_respuesta')
                        ->label('Respuesta')
                        ->live()
                        ->hidden()
                        ->columnSpanFull(),

                    Section::make('Respuesta')
                        ->schema(RespuestaFields::schema())
                        ->visible(fn (Get $get) => (bool) $get('tiene_respuesta'))
                        ->disabled($isEditing)
                        ->columns(2),

                    Section::make('Seguimiento')
                        ->hiddenOn('create')
                        ->schema([
                            // Solo se pide la fecha; la hora se registra sola por dentro (ver
                            // CreateConsulta), no se le pregunta al usuario.
                            DatePicker::make('fecha_consulta')->required()->default(now())->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            Select::make('estado')
                                // "Agendado" solo se ofrece cuando la consulta entra por
                                // WhatsApp (o ya está agendada), ver EstadoConsulta::inicialSegunOrigen.
                                ->options(fn (Get $get, $state) => collect([
                                    EstadoConsulta::Pendiente,
                                    EstadoConsulta::NoContesta,
                                    EstadoConsulta::Respondido,
                                    EstadoConsulta::SoloConsulta,
                                    EstadoConsulta::RemitirPsicologia,
                                    EstadoConsulta::RemitirSocial,
                                ])
                                    ->when(
                                        EstadoConsulta::esOrigenWhatsapp($get('origen'))
                                            || ($state instanceof EstadoConsulta ? $state : EstadoConsulta::tryFrom((string) $state)) === EstadoConsulta::Agendado,
                                        fn ($estados) => $estados->prepend(EstadoConsulta::Agendado),
                                    )
                                    ->mapWithKeys(fn (EstadoConsulta $e) => [$e->value => $e->getLabel()]))
                                ->required()
                                ->disabled($isEditing)
                                ->extraInputAttributes(['data-enter-nav' => 'true']),
                            TextInput::make('origen')
                                ->required()
                                ->datalist(Origenes::SUGERENCIAS)
                                ->regex('/^[^0-9]*$/')
                                ->disabled($isEditing)
                                // Si entra por WhatsApp, el estado pasa solo a "Agendado".
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    if (EstadoConsulta::esOrigenWhatsapp($state)) {
                                        $set('estado', EstadoConsulta::Agendado->value);
                                    }
                                })
                                ->extraInputAttributes(self::atributosSoloLetras()),
                            // Con origen "Talleres" se pide qué taller.
                            CamposOrigen::detalle($isEditing),
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
