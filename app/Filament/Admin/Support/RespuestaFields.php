<?php

namespace App\Filament\Admin\Support;

use App\Enums\Especialidad;
use App\Models\Delito;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Campos de "Materia legal / Delito / Respuesta" compartidos entre
 * ResponderConsultaAction (botón "Responder") y ConsultaForm (que usa
 * camposMateria() para clasificar la consulta misma, no una respuesta).
 */
class RespuestaFields
{
    public const OTRO = 'otro';

    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            ...self::camposMateria(),
            Textarea::make('respuesta')
                ->required()
                ->rows(3)
                ->columnSpanFull()
                ->extraInputAttributes(['data-enter-nav' => 'true']),
        ];
    }

    /**
     * Materia legal + delito, sin el texto de la respuesta: también se usan
     * para clasificar la consulta misma al crearla (ver ConsultaForm), no
     * solo al responderla.
     *
     * @return array<int, Component>
     */
    public static function camposMateria(string $etiquetaDelito = 'Delito / Materia específica'): array
    {
        return [
            Select::make('designacion')
                ->label('Materia legal')
                ->options(fn () => collect(Especialidad::cases())
                    ->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()])
                    ->put(self::OTRO, 'Otro'))
                ->native(false)
                ->live()
                ->required()
                ->afterStateUpdated(fn ($set) => $set('delito_id', null))
                ->extraAttributes(['data-enter-nav-field' => 'true', 'data-enter-nav-live' => 'true']),
            TextInput::make('materia_texto')
                ->label('Materia legal (manual)')
                ->maxLength(120)
                ->required()
                ->visible(fn (Get $get) => $get('designacion') === self::OTRO)
                ->extraInputAttributes(['data-enter-nav' => 'true']),
            Select::make('delito_id')
                ->label($etiquetaDelito)
                ->options(function (Get $get) {
                    $area = $get('designacion');

                    if (! $area) {
                        return [];
                    }

                    $opciones = $area === self::OTRO
                        ? []
                        : Delito::query()->where('area', $area)->orderBy('delito')->pluck('delito', 'id')->all();

                    return $opciones + [self::OTRO => 'Otro'];
                })
                ->searchable()
                ->native(false)
                ->live()
                ->disabled(fn (Get $get) => blank($get('designacion')))
                ->helperText(fn (Get $get) => blank($get('designacion')) ? 'Selecciona primero una materia legal.' : null)
                ->extraAttributes(['data-enter-nav-field' => 'true', 'data-enter-nav-live' => 'true']),
            TextInput::make('delito_texto')
                ->label('Delito (manual)')
                ->maxLength(255)
                ->required()
                ->visible(fn (Get $get) => $get('delito_id') === self::OTRO)
                ->extraInputAttributes(['data-enter-nav' => 'true']),
        ];
    }

    /**
     * Arma el array listo para `$consulta->respuestas()->create([...])` a
     * partir de los datos crudos del formulario (resuelve los escapes
     * "Otro" de materia/delito).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function datosParaGuardar(array $data): array
    {
        $esOtraMateria = ($data['designacion'] ?? null) === self::OTRO;
        $esOtroDelito = ($data['delito_id'] ?? null) === self::OTRO;

        return [
            'respuesta' => $data['respuesta'] ?? null,
            'designacion' => $esOtraMateria ? null : ($data['designacion'] ?? null),
            'materia_texto' => $esOtraMateria ? ($data['materia_texto'] ?? null) : null,
            'delito_id' => $esOtroDelito ? null : ($data['delito_id'] ?? null),
            'delito_texto' => $esOtroDelito ? ($data['delito_texto'] ?? null) : null,
        ];
    }
}
