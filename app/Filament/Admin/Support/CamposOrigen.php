<?php

namespace App\Filament\Admin\Support;

use App\Models\Personal;
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
        $llevaDetalle = fn (Get $get): bool => Origenes::llevaDetalle($get($campoOrigen));
        $esReferido = fn (Get $get): bool => Origenes::esReferido($get($campoOrigen));

        // Mismo campo para los dos casos: con "Referido" sugiere al personal
        // registrado, pero se puede escribir cualquier otro nombre a mano.
        return TextInput::make('origen_detalle')
            ->label(fn (Get $get): string => $esReferido($get) ? 'Referido por' : 'Detalle del taller')
            ->maxLength(255)
            ->datalist(fn (Get $get): array => $esReferido($get) ? self::personalRegistrado() : self::detallesPrevios())
            ->visible($llevaDetalle)
            ->required($llevaDetalle)
            ->disabled($deshabilitado)
            ->extraInputAttributes(['data-enter-nav' => 'true', 'autocomplete' => 'off']);
    }

    /**
     * Personal vigente (no retirado), para "Referido por".
     *
     * @return array<int, string>
     */
    public static function personalRegistrado(): array
    {
        return Personal::query()
            ->orderBy('nombres')
            ->orderBy('ap_paterno')
            ->get(['nombres', 'ap_paterno', 'ap_materno'])
            ->map(fn (Personal $p): string => trim($p->nombre_completo))
            ->filter()
            ->unique()
            ->values()
            ->all();
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
