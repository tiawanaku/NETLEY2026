<?php

namespace App\Filament\Admin\Resources\Casos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Formulario del informe de cierre de caso (reemplaza informe_cierre_caso.php).
 * Se usa como Action::schema() sobre CasoResource: al enviarse crea el informe
 * y transiciona el caso a "cerrado" (ver CasosTable::configure -> acción cerrarCaso).
 */
class CierreCasoSchema
{
    protected static array $resultados = [
        'se_gano_proceso' => 'Se ganó el proceso',
        'se_perdio_proceso' => 'Se perdió el proceso',
        'transaccion' => 'Transacción / acuerdo',
        'desistimiento' => 'Desistimiento',
        'archivo' => 'Archivo de obrados',
        'otro' => 'Otro',
    ];

    public static function make(): array
    {
        return [
            Select::make('resultado')
                ->options(self::$resultados)
                ->required(),
            DatePicker::make('fecha_cierre')->default(now())->required(),
            TextInput::make('saldo')->numeric()->prefix('Bs.')->default(0)->label('Saldo pendiente al cierre'),
            TextInput::make('perdida')->numeric()->prefix('Bs.')->default(0)->label('Pérdida (si aplica)'),
            TextInput::make('asume')->label('¿Quién asume el saldo/pérdida?'),
            TextInput::make('seguimiento_responsable')->label('Responsable de seguimiento posterior'),
            Textarea::make('opciones')->label('Opción de cierre')->columnSpanFull(),
            Textarea::make('nota_netley')->label('Nota interna')->columnSpanFull(),
        ];
    }
}
