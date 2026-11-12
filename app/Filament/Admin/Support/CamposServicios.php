<?php

namespace App\Filament\Admin\Support;

use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Closure;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Sección "Servicios requeridos" de los formularios de cita (Agendar y
 * Reagendar): Psicología, Trabajo social y "Otro", que al activarse abre al
 * lado un campo para escribir cuál.
 */
class CamposServicios
{
    public static function make(): Section
    {
        return Section::make('Servicios requeridos')
            ->schema([
                // El texto de "Otro" ocupa 2 de las 5 columnas (más espacio
                // para escribir).
                Grid::make(['default' => 1, 'sm' => 3, 'lg' => 5])
                    ->schema(array_map(
                        fn ($campo) => $campo instanceof TextInput ? $campo->columnSpan(['default' => 1, 'sm' => 3, 'lg' => 2]) : $campo,
                        self::interruptores(),
                    )),
            ])
            ->columnSpanFull();
    }

    /**
     * Mismos interruptores en un recuadro de una sola columna, para ir al
     * lado de otro campo (paso "Proceso" de Cliente ejecutivo, ficha del caso).
     */
    public static function campo(bool | Closure $disabled = false): Fieldset
    {
        return Fieldset::make('Servicios requeridos')
            ->schema([
                // Uno debajo del otro (el recuadro es angosto); el texto de
                // "Otro" debajo de su interruptor, a todo el ancho.
                Grid::make(1)
                    // De solo lectura (ficha del caso sin "Editar"), solo se
                    // muestran los servicios activados.
                    ->schema(array_map(
                        fn ($campo) => $campo instanceof TextInput
                            ? $campo->disabled($disabled)->columnSpanFull()
                            : $campo->disabled($disabled)
                                ->visible(fn (Toggle $component, $state): bool => ! $component->evaluate($disabled) || (bool) $state),
                        self::interruptores(),
                    ))
                    ->columnSpanFull(),
            ])
            // Y si no hay ninguno activado, no se muestra el recuadro.
            ->visible(fn (Fieldset $component, Get $get): bool => ! $component->evaluate($disabled)
                || $get('servicio_psicologia') || $get('servicio_trabajo_social') || $get('servicio_otro'))
            ->columnSpan(1);
    }

    /** @return array<int, \Filament\Forms\Components\Field> */
    protected static function interruptores(): array
    {
        // inlineLabel(false): los formularios donde van (paso "Proceso",
        // ficha del caso) usan etiquetas en línea, que en un recuadro
        // angosto dejaban el interruptor encima del texto; así queda el
        // interruptor con su texto al lado, uno debajo del otro.
        return [
            Toggle::make('servicio_psicologia')->label('Psicología')->inlineLabel(false),
            Toggle::make('servicio_trabajo_social')->label('Trabajo social')->inlineLabel(false),
            Toggle::make('servicio_otro')->label('Otro')->live()->inlineLabel(false),
            TextInput::make('servicio_otro_detalle')
                ->inlineLabel(false)
                ->hiddenLabel()
                ->placeholder('¿Qué servicio?')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => (bool) $get('servicio_otro'))
                ->required(fn (Get $get): bool => (bool) $get('servicio_otro'))
                ->validationAttribute('otro servicio')
                ->extraInputAttributes(['data-enter-nav' => 'true']),
        ];
    }

    /** Valores de la sección a partir de una cita o caso existente (para fillForm). */
    public static function desde(?Model $cita): array
    {
        return [
            'servicio_psicologia' => (bool) $cita?->servicio_psicologia,
            'servicio_trabajo_social' => (bool) $cita?->servicio_trabajo_social,
            'servicio_otro' => (bool) $cita?->servicio_otro,
            'servicio_otro_detalle' => $cita?->servicio_otro_detalle,
        ];
    }

    /** Columnas a guardar en la cita a partir de los datos del formulario. */
    public static function datos(array $data): array
    {
        $otro = (bool) ($data['servicio_otro'] ?? false);

        return [
            'servicio_psicologia' => (bool) ($data['servicio_psicologia'] ?? false),
            'servicio_trabajo_social' => (bool) ($data['servicio_trabajo_social'] ?? false),
            'servicio_otro' => $otro,
            'servicio_otro_detalle' => $otro ? trim((string) ($data['servicio_otro_detalle'] ?? '')) ?: null : null,
        ];
    }
}
