<?php

namespace App\Filament\Admin\Support;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Select con opción "Otros" que habilita un campo para escribir el valor.
 * Se guarda lo escrito en la misma columna (no la palabra "Otros"), y al
 * editar, un valor guardado que no está en la lista se muestra como
 * "Otros" + ese texto.
 */
class CampoConOtro
{
    /**
     * Select de un solo valor.
     *
     * @param  array<string, string>  $opciones  valor => etiqueta (incluye $valorOtro)
     * @return array{0: Select, 1: TextInput}
     */
    public static function simple(Select $select, array $opciones, string $valorOtro, string $etiquetaTexto, int $maxLength): array
    {
        $campo = $select->getName();
        $campoTexto = $campo.'_otro';
        $esOtro = fn (Get $get): bool => $get($campo) === $valorOtro;

        return [
            $select
                ->options($opciones)
                ->live()
                ->afterStateHydrated(function (Select $component, $state, Set $set) use ($opciones, $valorOtro, $campoTexto): void {
                    if (filled($state) && ! array_key_exists($state, $opciones)) {
                        $component->state($valorOtro);
                        $set($campoTexto, $state);
                    }
                })
                ->dehydrateStateUsing(fn ($state, Get $get) => $state === $valorOtro
                    ? (trim((string) $get($campoTexto)) ?: null)
                    : $state),

            TextInput::make($campoTexto)
                ->label($etiquetaTexto)
                ->maxLength($maxLength)
                ->required($esOtro)
                ->visible($esOtro)
                ->dehydrated(false)
                ->extraInputAttributes(['data-enter-nav' => 'true']),
        ];
    }

    /**
     * Select múltiple: "Otros" se reemplaza por lo escrito; varios valores se
     * separan con coma (ej. "Arquitecto, Ingeniero").
     *
     * @param  array<string, string>  $opciones  valor => etiqueta (incluye $valorOtro)
     * @return array{0: Select, 1: TextInput}
     */
    public static function multiple(Select $select, array $opciones, string $valorOtro, string $etiquetaTexto, int $maxLength): array
    {
        $campo = $select->getName();
        $campoTexto = $campo.'_otro';
        $esOtro = fn (Get $get): bool => in_array($valorOtro, (array) $get($campo), true);

        return [
            $select
                ->options($opciones)
                ->live()
                ->afterStateHydrated(function (Select $component, $state, Set $set) use ($opciones, $valorOtro, $campoTexto): void {
                    $valores = array_values(array_filter((array) $state, 'filled'));
                    $otros = array_values(array_filter($valores, fn ($v) => ! array_key_exists($v, $opciones)));

                    if ($otros !== []) {
                        $component->state([...array_values(array_diff($valores, $otros)), $valorOtro]);
                        $set($campoTexto, implode(', ', $otros));
                    }
                })
                ->dehydrateStateUsing(function ($state, Get $get) use ($valorOtro, $campoTexto): array {
                    $valores = array_values(array_filter((array) $state, fn ($v) => filled($v) && $v !== $valorOtro));

                    if (in_array($valorOtro, (array) $state, true)) {
                        $escritos = array_filter(array_map('trim', explode(',', (string) $get($campoTexto))), 'filled');
                        $valores = [...$valores, ...$escritos];
                    }

                    return array_values(array_unique($valores));
                }),

            TextInput::make($campoTexto)
                ->label($etiquetaTexto)
                ->helperText('Si son varias, sepáralas con coma.')
                ->maxLength($maxLength)
                ->required($esOtro)
                ->visible($esOtro)
                ->dehydrated(false)
                ->extraInputAttributes(['data-enter-nav' => 'true']),
        ];
    }
}
