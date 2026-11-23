<?php

namespace App\Filament\Admin\Support;

use App\Enums\Especialidad;
use App\Models\Caso;

/**
 * Etapas por materia (en el orden en que avanza un proceso) y tipos de
 * actuación del "Seguimiento del proceso". La etapa del último seguimiento
 * es la etapa actual del caso y se marca en la barra de avance.
 */
class EtapasProceso
{
    public const PENAL = [
        'Denuncia / querella',
        'Investigación preliminar',
        'Imputación formal',
        'Etapa preparatoria',
        'Acusación',
        'Juicio oral',
        'Sentencia',
        'Apelación',
        'Casación',
        'Ejecución de sentencia',
    ];

    public const CIVIL = [
        'Medidas preparatorias',
        'Demanda',
        'Admisión y citación',
        'Contestación',
        'Audiencia preliminar',
        'Audiencia complementaria / prueba',
        'Sentencia',
        'Apelación',
        'Casación',
        'Ejecución de sentencia',
    ];

    public const FAMILIA = [
        'Conciliación previa',
        'Demanda',
        'Admisión y citación',
        'Contestación',
        'Audiencia preliminar',
        'Audiencia complementaria / prueba',
        'Sentencia',
        'Apelación',
        'Casación',
        'Ejecución / homologación',
    ];

    public const LABORAL = [
        'Reclamo administrativo (Ministerio de Trabajo)',
        'Demanda',
        'Citación',
        'Contestación',
        'Periodo de prueba',
        'Sentencia',
        'Apelación',
        'Casación',
        'Ejecución de sentencia',
    ];

    public const GENERAL = [
        'Inicio / preparación',
        'Presentación',
        'Trámite',
        'Audiencias',
        'Resolución',
        'Recursos',
        'Ejecución',
    ];

    public const ACTUACIONES = [
        'Audiencia',
        'Memorial presentado',
        'Notificación',
        'Resolución / decreto',
        'Revisión de expediente',
        'Reunión con el cliente',
        'Diligencia / trámite',
        'Declaración',
        'Pericia',
        'Otro',
    ];

    /** @return array<int, string> */
    public static function de(?Caso $caso): array
    {
        return match ($caso?->especialidad) {
            Especialidad::Penal => self::PENAL,
            Especialidad::Civil => self::CIVIL,
            Especialidad::Familia => self::FAMILIA,
            Especialidad::Laboral => self::LABORAL,
            default => self::GENERAL,
        };
    }

    /**
     * Opciones del selector: las etapas de la materia más, si el caso ya
     * tiene alguna etapa distinta registrada, esa también (no se pierde).
     *
     * @return array<string, string>
     */
    public static function opciones(?Caso $caso): array
    {
        $etapas = self::de($caso);

        if ($caso) {
            $etapas = array_merge($etapas, $caso->seguimientos()->whereNotNull('etapa_proceso')
                ->pluck('etapa_proceso')->diff($etapas)->unique()->all());
        }

        return array_combine($etapas, $etapas);
    }

    /**
     * Instancias del caso para el selector: fiscalías, juzgados y otras
     * instancias registradas (las vigentes primero).
     *
     * @return array<string, string>
     */
    public static function instancias(?Caso $caso): array
    {
        if (! $caso) {
            return [];
        }

        $lista = collect();

        foreach ($caso->fiscalias()->orderByDesc('vigente')->get() as $f) {
            $lista->push('Fiscalía'.($f->fiscalia_num ? ' '.$f->fiscalia_num : '').($f->num_caso ? ' — caso '.$f->num_caso : ''));
        }

        foreach ($caso->juzgados()->orderByDesc('vigente')->get() as $j) {
            $lista->push('Juzgado'.($j->juzgado_num ? ' '.$j->juzgado_num : '').($j->num_caso ? ' — caso '.$j->num_caso : ''));
        }

        foreach ($caso->otrosSeguimientos as $o) {
            $lista->push(($o->nombre_instancia ?: 'Otra instancia').($o->num_caso ? ' — caso '.$o->num_caso : ''));
        }

        $lista = $lista->map(fn ($v) => trim($v))->unique()->values();

        return $lista->combine($lista)->all();
    }

    /** Instancia vigente (la que hoy lleva el caso), en texto. */
    public static function instanciaVigente(Caso $caso): ?string
    {
        if ($j = $caso->juzgados()->where('vigente', true)->latest('fecha_inicio')->first()) {
            return trim('Juzgado'.($j->juzgado_num ? ' '.$j->juzgado_num : '').($j->nombre_juez ? ' · Juez: '.$j->nombre_juez : ''));
        }

        if ($f = $caso->fiscalias()->where('vigente', true)->latest('fecha_inicio')->first()) {
            return trim('Fiscalía'.($f->fiscalia_num ? ' '.$f->fiscalia_num : '').($f->nombre_fiscal ? ' · Fiscal: '.$f->nombre_fiscal : ''));
        }

        $o = $caso->otrosSeguimientos()->whereNull('fecha_fin')->latest('fecha_inicio')->first();

        return $o ? ($o->nombre_instancia ?: 'Otra instancia') : null;
    }
}
