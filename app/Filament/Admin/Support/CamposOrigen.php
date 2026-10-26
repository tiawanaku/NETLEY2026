<?php

namespace App\Filament\Admin\Support;

use App\Support\Origenes;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;

/**
 * Campo "Detalle del taller" que aparece justo debajo de "Origen" cuando se
 * elige (o escribe) "Talleres". Se usa en las fichas de Consulta y Llamada.
 * Sugiere los talleres ya cargados (detalles anteriores y el origen de los
 * registros del módulo Talleres) para escribirlos siempre igual.
 */
class CamposOrigen
{
    public static function detalle(bool|Closure $deshabilitado = false, string $campoOrigen = 'origen'): TextInput
    {
        $esTalleres = fn (Get $get): bool => Origenes::esTalleres($get($campoOrigen));

        return TextInput::make('origen_detalle')
            ->label('Detalle del taller')
            ->placeholder('Ej. Taller colegio San Calixto')
            ->helperText('Qué taller: institución, lugar o fecha.')
            ->maxLength(255)
            ->datalist(fn (): array => self::detallesPrevios())
            ->visible($esTalleres)
            ->required($esTalleres)
            ->disabled($deshabilitado)
            ->extraInputAttributes(['data-enter-nav' => 'true', 'autocomplete' => 'off']);
    }

    /**
     * @return array<int, string>
     */
    public static function detallesPrevios(): array
    {
        $columna = fn (string $tabla, string $campo) => DB::table($tabla)
            ->whereNotNull($campo)
            ->where($campo, '!=', '')
            ->distinct()
            ->orderBy($campo)
            ->limit(100)
            ->pluck($campo);

        return $columna('consultas', 'origen_detalle')
            ->merge($columna('llamadas', 'origen_detalle'))
            ->merge($columna('talleres', 'origen'))
            ->map(fn ($valor): string => trim((string) $valor))
            ->filter()
            ->unique(fn (string $valor): string => mb_strtolower($valor))
            ->sort()
            ->values()
            ->all();
    }
}
