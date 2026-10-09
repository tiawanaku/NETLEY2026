<?php

namespace App\Filament\Admin\Resources\Consultas\Schemas;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Support\CamposTelefono;
use App\Support\PaisesCiudades;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                        // País/Provincia/Ciudad usan la API pública countriesnow.space
                        // como sugerencias (datalist), en cascada; siguen siendo texto
                        // libre porque esa API no cubre poblaciones pequeñas de Bolivia.
                        TextInput::make('pais')
                            ->default('Bolivia')
                            ->live(onBlur: true)
                            ->datalist(fn () => PaisesCiudades::paises())
                            ->regex('/^[^0-9]*$/')
                            ->disabled($isEditing)
                            ->extraInputAttributes(self::atributosSoloLetras()),
                        TextInput::make('provincia')
                            ->live(onBlur: true)
                            ->datalist(fn (Get $get) => PaisesCiudades::provincias($get('pais')))
                            ->regex('/^[^0-9]*$/')
                            ->disabled($isEditing)
                            ->extraInputAttributes(self::atributosSoloLetras()),
                        TextInput::make('ciudad')
                            ->datalist(fn (Get $get) => PaisesCiudades::ciudades($get('pais'), $get('provincia')))
                            ->regex('/^[^0-9]*$/')
                            ->disabled($isEditing)
                            ->extraInputAttributes(self::atributosSoloLetras()),
                        TextInput::make('direccion')->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                    ]),

                Group::make([
                    Section::make('La consulta')
                        ->schema([
                            Textarea::make('consulta')->required()->rows(4)->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            Textarea::make('nota_interna')->label('Nota interna')->rows(2)->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                        ]),

                    Section::make('Seguimiento')
                        ->schema([
                            // Solo se pide la fecha; la hora se registra sola por dentro (ver
                            // CreateConsulta), no se le pregunta al usuario.
                            DatePicker::make('fecha_consulta')->required()->default(now())->disabled($isEditing)->extraInputAttributes(['data-enter-nav' => 'true']),
                            Select::make('estado')
                                ->options(EstadoConsulta::class)
                                ->required()
                                ->disabled($isEditing)
                                ->extraInputAttributes(['data-enter-nav' => 'true']),
                            TextInput::make('origen')
                                ->required()
                                ->datalist(self::$origenes)
                                ->regex('/^[^0-9]*$/')
                                ->helperText('Elige una sugerencia o escribe un origen libre (ej. "taller colegio: San Calixto").')
                                ->disabled($isEditing)
                                ->extraInputAttributes(self::atributosSoloLetras()),
                        ]),
                ]),
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
