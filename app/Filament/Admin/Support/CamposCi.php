<?php

namespace App\Filament\Admin\Support;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\FusedGroup;

/**
 * "Carnet de identidad" + "Expedido en" fusionados en un mismo cuadro,
 * estilo CamposTelefono (código de país + número). A diferencia de
 * CamposTelefono, acá cada uno se guarda en su propia columna (`ci` /
 * `extension`), no se componen en una sola — solo van juntos visualmente.
 */
class CamposCi
{
    /**
     * Los 9 departamentos de Bolivia más "QR" (carnet con código QR, sin
     * departamento de expedición impreso).
     *
     * @return array<string, string>
     */
    public static function opcionesExtension(): array
    {
        return [
            'LP' => 'La Paz',
            'CB' => 'Cochabamba',
            'SC' => 'Santa Cruz',
            'OR' => 'Oruro',
            'PT' => 'Potosí',
            'TJ' => 'Tarija',
            'CH' => 'Chuquisaca',
            'BE' => 'Beni',
            'PD' => 'Pando',
            'QR' => 'QR',
        ];
    }

    /** @param  bool|\Closure  $bloqueado  solo lectura (ej. ficha del cliente, ver ClienteForm) */
    public static function make(bool|\Closure $bloqueado = false): FusedGroup
    {
        return FusedGroup::make([
            Select::make('extension')
                ->label('Expedido en')
                ->hiddenLabel()
                // Un valor antiguo fuera de la lista se agrega para que se
                // siga viendo (clientes importados del sistema anterior).
                ->options(fn (?string $state) => filled($state) && ! array_key_exists($state, self::opcionesExtension())
                    ? [...self::opcionesExtension(), $state => $state]
                    : self::opcionesExtension())
                ->disabled($bloqueado)
                ->native(false)
                ->selectablePlaceholder(false)
                ->default('LP')
                ->extraAttributes(['data-enter-nav-field' => 'true'])
                ->columnSpan(1),
            TextInput::make('ci')
                ->label('Carnet de identidad')
                ->hiddenLabel()
                ->placeholder('Número')
                ->disabled($bloqueado)
                ->required()
                ->regex('/^[0-9]+$/')
                ->validationMessages(['regex' => 'Solo se permiten números.'])
                ->extraInputAttributes([
                    'data-enter-nav' => 'true',
                    'inputmode' => 'numeric',
                    'oninput' => 'this.value=this.value.replace(/[^0-9]/g,\'\')',
                ])
                ->columnSpan(1),
        ])
            ->label('Carnet de identidad')
            ->columns(['default' => 2])
            // Ver .campo-ci en admin-theme.css: mismo criterio que
            // .campo-telefono (el selector toma el ancho de lo elegido, el
            // número ocupa el resto).
            ->extraAttributes(['class' => 'campo-ci']);
    }
}
