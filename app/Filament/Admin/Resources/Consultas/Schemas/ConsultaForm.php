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
use App\Support\Telefono;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                        TextInput::make('correo')->email()->disabled($isEditing)->rules([ReglasContacto::correo(Consulta::class, 'otra consulta')])
                            // Valida apenas se completa el campo (al perder el foco), no
                            // recién al guardar.
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (TextInput $component) => $component->getLivewire()->validateOnly($component->getStatePath()))
                            ->extraInputAttributes(['data-enter-nav' => 'true']),
                        ...CamposDomicilio::region($isEditing),
                    ]),

                Group::make([
                    // Tanto al crear como al editar, en esta columna se pide la
                    // materia legal, el tipo de proceso y la nota interna, y
                    // debajo el Seguimiento (fecha de consulta, estado y
                    // origen). El texto de la consulta y la respuesta (se
                    // sigue registrando con el botón "Responder" aparte) no
                    // se muestran acá.
                    Section::make('Consulta')
                        // Solo en consultas que llegaron por la portada: abre el
                        // chat de WhatsApp con la persona (al teléfono de WhatsApp,
                        // o al de contacto si no dejó otro), en una pestaña nueva.
                        ->headerActions([
                            Action::make('responderWhatsapp')
                                ->label('Responder por WhatsApp')
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->color('success')
                                ->visible(fn (string $operation, ?Consulta $record): bool => $operation === 'edit'
                                    && self::urlWhatsappConsulta($record) !== null)
                                ->url(fn (?Consulta $record): ?string => self::urlWhatsappConsulta($record))
                                ->openUrlInNewTab(),
                        ])
                        ->schema([
                            // Lo que escribió la persona en el formulario de la portada.
                            Textarea::make('consulta')
                                ->label('Consulta')
                                ->rows(4)
                                ->disabled($isEditing)
                                ->visible(fn (string $operation, Get $get): bool => $operation === 'edit'
                                    && self::esDeLaPortada($get('origen')))
                                ->extraInputAttributes(['data-enter-nav' => 'true']),
                            ...RespuestaFields::camposMateria(etiquetaDelito: 'Tipo de proceso', deshabilitado: $isEditing),
                            Textarea::make('nota_interna')->label('Nota interna')->rows(2)->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                        ])
                        ->columns(1),

                    Section::make('La consulta')
                        ->schema([
                            Textarea::make('consulta')->required()->rows(4)->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            // Se cargan con la acción "Agendar"; se sincronizan
                            // ahí, no se editan acá.
                            CamposDenuncia::denunciado()->disabled($isEditing),
                            CamposDenuncia::denuncias(conRelacion: true)->disabled($isEditing),
                        ])
                        ->hidden(),

                    Section::make('Seguimiento')
                        ->schema([
                            // Solo se pide la fecha; la hora se registra sola por dentro (ver
                            // CreateConsulta), no se le pregunta al usuario.
                            DatePicker::make('fecha_consulta')->required()->default(now())->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            Select::make('estado')
                                // "Agendado" solo se ofrece cuando la consulta entra por
                                // WhatsApp (o ya está agendada), ver EstadoConsulta::inicialSegunOrigen.
                                ->options(function (Get $get, $state) {
                                    $actual = $state instanceof EstadoConsulta ? $state : EstadoConsulta::tryFrom((string) $state);

                                    return collect([
                                        EstadoConsulta::Pendiente,
                                        EstadoConsulta::NoContesta,
                                        EstadoConsulta::Respondido,
                                        EstadoConsulta::SoloConsulta,
                                        EstadoConsulta::RemitirPsicologia,
                                        EstadoConsulta::RemitirSocial,
                                    ])
                                        ->when(
                                            EstadoConsulta::esOrigenWhatsapp($get('origen')) || $actual === EstadoConsulta::Agendado,
                                            fn ($estados) => $estados->prepend(EstadoConsulta::Agendado),
                                        )
                                        // Estados que se asignan solos (p. ej. "Caso iniciado" al
                                        // ascender a caso): no se ofrecen para elegir, pero si la
                                        // consulta ya lo tiene debe mostrarse, no quedar en blanco.
                                        ->when(
                                            $actual !== null && ! in_array($actual, [
                                                EstadoConsulta::Pendiente,
                                                EstadoConsulta::NoContesta,
                                                EstadoConsulta::Respondido,
                                                EstadoConsulta::SoloConsulta,
                                                EstadoConsulta::RemitirPsicologia,
                                                EstadoConsulta::RemitirSocial,
                                                EstadoConsulta::Agendado,
                                            ], true),
                                            fn ($estados) => $estados->push($actual),
                                        )
                                        ->mapWithKeys(fn (EstadoConsulta $e) => [$e->value => $e->getLabel()]);
                                })
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

    /** Las consultas del formulario público de la portada nacen con origen "Sitio web". */
    protected static function esDeLaPortada(?string $origen): bool
    {
        return mb_strtolower(trim((string) $origen)) === 'sitio web';
    }

    /** Enlace de WhatsApp con un saludo ya escrito, solo para consultas de la portada. */
    protected static function urlWhatsappConsulta(?Consulta $consulta): ?string
    {
        if (! $consulta || ! self::esDeLaPortada($consulta->origen)) {
            return null;
        }

        $url = Telefono::urlWhatsapp($consulta->whatsapp ?: $consulta->telefono);

        if ($url === null) {
            return null;
        }

        $saludo = 'Hola '.trim((string) $consulta->nombres).', te escribimos de NETLEY & Asociados por la consulta que enviaste desde nuestra página web.';

        return $url.'?text='.rawurlencode($saludo);
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
