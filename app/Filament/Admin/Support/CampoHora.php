<?php

namespace App\Filament\Admin\Support;

use Filament\Forms\Components\TextInput;

/**
 * Hora (HH:MM, 24 horas) que se escribe directo por teclado. Reemplaza al
 * TimePicker de Filament: el nativo muestra AM/PM según la región del
 * equipo y el no nativo tiene el campo en solo lectura (solo se puede usar
 * con las flechitas del desplegable).
 */
class CampoHora
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->placeholder('HH:MM')
            ->mask('99:99')
            ->maxLength(5)
            ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
            ->validationMessages(['regex' => 'Escribe la hora en formato 24 horas, por ejemplo 14:30.'])
            ->formatStateUsing(fn ($state): ?string => filled($state) ? substr((string) $state, 0, 5) : null)
            ->extraInputAttributes(['inputmode' => 'numeric', 'autocomplete' => 'off', 'data-enter-nav' => 'true']);
    }

    public static function esValida(?string $hora): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $hora);
    }
}
