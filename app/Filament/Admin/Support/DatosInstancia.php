<?php

namespace App\Filament\Admin\Support;

use App\Enums\Especialidad;
use App\Models\Caso;
use App\Models\Fiscalia;
use App\Models\Juzgado;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;

/**
 * "Datos del proceso": quién lleva el caso hoy, según la materia.
 * - Penal: Ministerio Público (fiscal, secretaria, investigador, otro
 *   funcionario) y Órgano judicial (juez, secretaria).
 * - Civil / Familia / Laboral: Juzgado (juez, secretaria).
 *
 * No son columnas del caso: se leen y guardan en la fiscalía y el juzgado
 * vigentes (los mismos de la pestaña "Datos judiciales"). Si no hay uno
 * vigente y se llena algún dato, se crea.
 */
class DatosInstancia
{
    public const CAMPO = 'datos_instancia';

    private const FISCALIA = ['fiscalia_num', 'nombre_fiscal', 'secretaria', 'investigador', 'otro_funcionario'];

    private const JUZGADO = ['juzgado_num', 'nombre_juez', 'secretaria'];

    public static function esPenal(Get $get): bool
    {
        return $get('especialidad') === Especialidad::Penal->value
            || $get('especialidad') === Especialidad::Penal;
    }

    public static function campos(\Closure $deshabilitado): Group
    {
        $penal = fn (Get $get) => self::esPenal($get);
        $conMateria = fn (Get $get) => filled($get('especialidad'));
        $texto = fn (string $nombre, string $label) => TextInput::make($nombre)->label($label)->maxLength(150)
            ->disabled($deshabilitado)->extraInputAttributes(['data-enter-nav' => 'true']);

        return Group::make([
            Group::make([
                $texto(self::CAMPO.'.fiscalia.fiscalia_num', 'Ministerio Público')
                    ->placeholder('Ej.: Fiscalía Especializada en Delitos de Violencia Sexual y en Razón de Género')
                    ->columnSpanFull()->extraFieldWrapperAttributes(['class' => 'netley-fila-completa']),
                $texto(self::CAMPO.'.fiscalia.nombre_fiscal', 'Fiscal'),
                $texto(self::CAMPO.'.fiscalia.secretaria', 'Secretaria'),
                $texto(self::CAMPO.'.fiscalia.investigador', 'Investigador'),
                $texto(self::CAMPO.'.fiscalia.otro_funcionario', 'Otro funcionario'),
            ])->columns(['default' => 1, 'lg' => 2])->columnSpanFull()->visible($penal),
            Group::make([
                $texto(self::CAMPO.'.juzgado.juzgado_num', 'Juzgado')
                    ->label(fn (Get $get) => self::esPenal($get) ? 'Órgano judicial' : 'Juzgado')
                    ->placeholder(fn (Get $get) => self::esPenal($get) ? 'Ej.: Juzgado de Instrucción Penal N° 14' : 'Ej.: Juzgado Público de Familia N° 3')
                    ->columnSpanFull()->extraFieldWrapperAttributes(['class' => 'netley-fila-completa']),
                $texto(self::CAMPO.'.juzgado.nombre_juez', 'Juez'),
                $texto(self::CAMPO.'.juzgado.secretaria', 'Secretaria'),
            ])->columns(['default' => 1, 'lg' => 2])->columnSpanFull(),
        ])
            ->columns(['default' => 1, 'lg' => 2])
            ->columnSpanFull()
            ->visible($conMateria);
    }

    public static function fiscalia(Caso $caso): ?Fiscalia
    {
        return $caso->fiscalias()->where('vigente', true)->orderByDesc('fecha_inicio')->orderByDesc('id')->first();
    }

    public static function juzgado(Caso $caso): ?Juzgado
    {
        return $caso->juzgados()->where('vigente', true)->orderByDesc('fecha_inicio')->orderByDesc('id')->first();
    }

    /** @return array{fiscalia: array<string, ?string>, juzgado: array<string, ?string>} */
    public static function cargar(Caso $caso): array
    {
        $f = self::fiscalia($caso);
        $j = self::juzgado($caso);

        return [
            'fiscalia' => collect(self::FISCALIA)->mapWithKeys(fn ($c) => [$c => $f?->{$c}])->all(),
            'juzgado' => collect(self::JUZGADO)->mapWithKeys(fn ($c) => [$c => $j?->{$c}])->all(),
        ];
    }

    /** Guarda lo que llegó del formulario (los grupos ocultos no llegan). */
    public static function guardar(Caso $caso, ?array $datos): void
    {
        if (! $datos) {
            return;
        }

        if (isset($datos['fiscalia'])) {
            self::guardarEn(self::fiscalia($caso), fn () => $caso->fiscalias(), self::FISCALIA, $datos['fiscalia']);
        }

        if (isset($datos['juzgado'])) {
            self::guardarEn(self::juzgado($caso), fn () => $caso->juzgados(), self::JUZGADO, $datos['juzgado']);
        }
    }

    private static function guardarEn($registro, \Closure $relacion, array $columnas, array $datos): void
    {
        $valores = collect($columnas)->mapWithKeys(fn ($c) => [$c => filled($datos[$c] ?? null) ? trim($datos[$c]) : null])->all();

        if ($registro) {
            $registro->update($valores);
        } elseif (collect($valores)->filter()->isNotEmpty()) {
            $relacion()->create($valores + ['vigente' => true, 'fecha_inicio' => now()->toDateString()]);
        }
    }
}
