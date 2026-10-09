<?php

namespace App\Filament\Admin\Forms\Components;

use App\Enums\Especialidad;
use App\Models\Delito;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Par de selects en cascada Área -> Delito, respaldados por el catálogo `delitos`.
 * Reemplaza la lógica AJAX duplicada (api_delitos.php / api_delitos_materia.php)
 * que el sistema legacy reimplementaba de forma independiente en cada formulario.
 *
 * Uso: ...DelitoSelect::make(areaField: 'especialidad', delitoField: 'delito_id')
 */
class DelitoSelect
{
    /** Valor del área que significa "Otros": la materia se escribe a mano en materia_texto. */
    public const OTROS = 'otro';

    /**
     * Con $conOtros:
     * - el área suma "Otros", que habilita "Materia legal (especificar)"
     *   (campo materia_texto);
     * - el delito suma "Otro" en cualquier área, que habilita "Delito
     *   (especificar)" (campo delito_texto). Con materia "Otros" no hay
     *   catálogo, así que el delito se fija en "Otro" y se escribe a mano.
     *
     * @return array<int, Component>
     */
    public static function make(
        string $areaField = 'especialidad',
        string $delitoField = 'delito_id',
        string $areaLabel = 'Área / Materia legal',
        string $delitoLabel = 'Delito / Materia específica',
        bool $required = true,
        bool $conOtros = false,
    ): array {
        $esOtros = fn (Get $get): bool => $conOtros && $get($areaField) === self::OTROS;
        $esOtroDelito = fn (Get $get): bool => $conOtros && $get($delitoField) === self::OTROS;

        return array_values(array_filter([
            Select::make($areaField)
                ->label($areaLabel)
                ->options(fn () => collect(Especialidad::cases())
                    ->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()])
                    ->when($conOtros, fn ($opciones) => $opciones->put(self::OTROS, 'Otros')))
                ->native(false)
                ->live()
                ->required($required)
                ->afterStateUpdated(fn ($set, $state) => $set($delitoField, $conOtros && $state === self::OTROS ? self::OTROS : null))
                ->extraAttributes(['data-enter-nav-field' => 'true', 'data-enter-nav-live' => 'true']),

            $conOtros
                ? TextInput::make('materia_texto')
                    ->label('Materia legal (especificar)')
                    ->maxLength(120)
                    ->required($esOtros)
                    ->visible($esOtros)
                    ->extraInputAttributes(['data-enter-nav' => 'true'])
                : null,

            Select::make($delitoField)
                ->label($delitoLabel)
                ->options(function (Get $get) use ($areaField, $conOtros) {
                    $area = $get($areaField);

                    if (! $area) {
                        return [];
                    }

                    $opciones = $area === self::OTROS
                        ? []
                        : Delito::query()->where('area', $area)->orderBy('delito')->pluck('delito', 'id')->all();

                    return $conOtros ? $opciones + [self::OTROS => 'Otro'] : $opciones;
                })
                ->searchable()
                ->native(false)
                ->live()
                ->disabled(fn (Get $get) => blank($get($areaField)))
                ->required($required)
                ->helperText(fn (Get $get) => blank($get($areaField)) ? 'Selecciona primero un área.' : null)
                ->extraAttributes(['data-enter-nav-field' => 'true', 'data-enter-nav-live' => 'true']),

            $conOtros
                ? TextInput::make('delito_texto')
                    ->label('Delito (especificar)')
                    ->maxLength(255)
                    ->required($esOtroDelito)
                    ->visible($esOtroDelito)
                    ->extraInputAttributes(['data-enter-nav' => 'true'])
                : null,
        ]));
    }
}
