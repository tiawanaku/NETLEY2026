<?php

namespace App\Filament\Admin\Forms\Components;

use App\Enums\Especialidad;
use App\Models\Delito;
use Filament\Forms\Components\Select;
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
    /**
     * @return array<int, Component>
     */
    public static function make(
        string $areaField = 'especialidad',
        string $delitoField = 'delito_id',
        string $areaLabel = 'Área / Materia legal',
        string $delitoLabel = 'Delito / Materia específica',
        bool $required = true,
    ): array {
        return [
            Select::make($areaField)
                ->label($areaLabel)
                ->options(fn () => collect(Especialidad::cases())->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()]))
                ->native(false)
                ->live()
                ->required($required)
                ->afterStateUpdated(fn ($set) => $set($delitoField, null))
                ->extraAttributes(['data-enter-nav-field' => 'true', 'data-enter-nav-live' => 'true']),

            Select::make($delitoField)
                ->label($delitoLabel)
                ->options(function (Get $get) use ($areaField) {
                    $area = $get($areaField);

                    if (! $area) {
                        return [];
                    }

                    return Delito::query()
                        ->where('area', $area)
                        ->orderBy('delito')
                        ->pluck('delito', 'id');
                })
                ->searchable()
                ->native(false)
                ->disabled(fn (Get $get) => blank($get($areaField)))
                ->required($required)
                ->helperText(fn (Get $get) => blank($get($areaField)) ? 'Selecciona primero un área.' : null)
                ->extraAttributes(['data-enter-nav-field' => 'true']),
        ];
    }
}
