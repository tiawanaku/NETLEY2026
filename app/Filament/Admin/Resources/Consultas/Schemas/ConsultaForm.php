<?php

namespace App\Filament\Admin\Resources\Consultas\Schemas;

use App\Enums\EstadoConsulta;
use App\Enums\FormaIngreso;
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
     * @param  array<int, \Filament\Schemas\Components\Component>  $after  Componentes a mostrar justo debajo de la sección "Consulta" (ej. "Cerrar consulta" en VerAgendado).
     * @param  bool  $conSeguimiento  false la omite por completo (sin dejar el espacio): la usa VerAgendado, que no la necesita.
     */
    public static function configure(Schema $schema, array $before = [], array $after = [], bool $conSeguimiento = true): Schema
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
                            Select::make('forma_ingreso')
                                ->label('Forma de ingreso')
                                ->options(FormaIngreso::class)
                                ->native(false)
                                ->required()
                                ->disabled($isEditing)
                                // ->native(false): es un combobox, no un <select>
                                // nativo, así que la marca de Enter va en el
                                // contenedor (extraAttributes), no en el input
                                // oculto (extraInputAttributes no llega al botón
                                // real — ver enter-navigation.js).
                                ->extraAttributes(['data-enter-nav-field' => 'true']),
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

                    ...$after,

                    ...($conSeguimiento ? [Section::make('Seguimiento')
                        ->schema([
                            // Solo se pide la fecha; la hora se registra sola por dentro (ver
                            // CreateConsulta), no se le pregunta al usuario.
                            DatePicker::make('fecha_consulta')->required()->default(now())->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            Select::make('origen')
                                // Opciones fijas (antes era texto libre con
                                // sugerencias) + "Otro" al final para escribirlo
                                // a mano (ver 'origen_otro' debajo).
                                ->options(
                                    collect(Origenes::SUGERENCIAS)
                                        ->mapWithKeys(fn (string $o) => [$o => $o])
                                        ->put(Origenes::OTRO, 'Otro')
                                )
                                ->native(false)
                                ->required()
                                ->disabled($isEditing)
                                // Un registro antiguo con un origen que no está en
                                // la lista se muestra como "Otro", con ese texto
                                // ya cargado en 'origen_otro'.
                                ->afterStateHydrated(function (Set $set, ?string $state): void {
                                    if (filled($state) && ! in_array($state, Origenes::SUGERENCIAS, true) && $state !== Origenes::OTRO) {
                                        $set('origen_otro', $state);
                                        $set('origen', Origenes::OTRO);
                                    }
                                })
                                // Si entra por WhatsApp, el estado pasa solo a "Agendado".
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    if (EstadoConsulta::esOrigenWhatsapp($state)) {
                                        $set('estado', EstadoConsulta::Agendado->value);
                                    }
                                })
                                ->extraAttributes(['data-enter-nav-field' => 'true']),
                            TextInput::make('origen_otro')
                                ->label('Origen (especificar)')
                                ->maxLength(100)
                                ->required(fn (Get $get) => $get('origen') === Origenes::OTRO)
                                ->visible(fn (Get $get) => $get('origen') === Origenes::OTRO)
                                ->disabled($isEditing)
                                ->extraInputAttributes(['data-enter-nav' => 'true']),
                            // Con origen "Talleres" se pide qué taller.
                            CamposOrigen::detalle($isEditing),
                            Select::make('estado')
                                // Solo Pendiente/Agendado se ofrecen para elegir; otros
                                // estados (NoContesta, Respondido, Caso Iniciado, etc.) se
                                // asignan solos desde otras acciones, pero si la consulta ya
                                // los tiene deben mostrarse igual, no quedar en blanco.
                                ->options(function ($state) {
                                    $actual = $state instanceof EstadoConsulta ? $state : EstadoConsulta::tryFrom((string) $state);

                                    return collect([EstadoConsulta::Pendiente, EstadoConsulta::Agendado])
                                        ->when(
                                            $actual !== null && ! in_array($actual, [EstadoConsulta::Pendiente, EstadoConsulta::Agendado], true),
                                            fn ($estados) => $estados->push($actual),
                                        )
                                        // "Agendado" (internamente, App\Enums\EstadoConsulta) es
                                        // el estado real que solo corresponde si la consulta
                                        // tiene una cita vigente (ver AgendarCitaAction, que lo
                                        // fuerza al crear una cita). Elegir esta opción acá es
                                        // la intención de agendar, no el hecho consumado — por
                                        // eso en el select se lee "Agendar", aunque el valor
                                        // guardado siga siendo 'agendado' y abre el modal de
                                        // Agendar al guardar (ver CreateConsulta::getRedirectUrl()).
                                        ->mapWithKeys(fn (EstadoConsulta $e) => [
                                            $e->value => $e === EstadoConsulta::Agendado ? 'Agendar' : $e->getLabel(),
                                        ]);
                                })
                                ->native(false)
                                ->required()
                                ->disabled($isEditing)
                                ->extraAttributes(['data-enter-nav-field' => 'true']),
                        ])] : []),
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

    /**
     * Resuelve el select "Origen" + "Otro" (origen_otro) al valor real a
     * guardar en la columna `origen`, y descarta el campo auxiliar (no
     * existe en la tabla). Se usa al crear y al editar.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function resolverOrigen(array $data): array
    {
        if (($data['origen'] ?? null) === Origenes::OTRO) {
            $data['origen'] = trim((string) ($data['origen_otro'] ?? ''));
        }

        unset($data['origen_otro']);

        return $data;
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
