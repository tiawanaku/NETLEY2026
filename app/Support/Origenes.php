<?php

namespace App\Support;

/**
 * Orígenes de una consulta o llamada (por dónde llegó el contacto). El
 * campo "origen" es texto libre con sugerencias (datos legados con otros
 * valores siguen siendo válidos). Con "Talleres" se pide además el detalle
 * del taller en "origen_detalle"; con cualquier otro origen ese detalle se
 * descarta al guardar (ver limpiarDetalle()).
 */
class Origenes
{
    public const TALLERES = 'Talleres';

    /** @var array<int, string> */
    public const SUGERENCIAS = [
        'Cliente antiguo',
        'Recomendación',
        'Espontáneo',
        'Página Netley',
        'Facebook',
        'WhatsApp',
        'TikTok',
        'Físico',
        self::TALLERES,
    ];

    /** "Talleres", "taller", "TALLER colegio…": cualquier origen que empiece por "taller". */
    public static function esTalleres(?string $origen): bool
    {
        return str_starts_with(mb_strtolower(trim((string) $origen)), 'taller');
    }

    /**
     * Detalle a guardar: solo con origen Talleres y no vacío; si no, null.
     */
    public static function limpiarDetalle(?string $origen, ?string $detalle): ?string
    {
        $detalle = trim((string) $detalle);

        return self::esTalleres($origen) && $detalle !== '' ? $detalle : null;
    }

    /** Texto para mostrar: "Talleres — Colegio San Calixto" o solo el origen. */
    public static function texto(?string $origen, ?string $detalle): ?string
    {
        $origen = trim((string) $origen);
        $detalle = self::limpiarDetalle($origen, $detalle);

        if ($origen === '') {
            return null;
        }

        return $detalle !== null ? $origen.' — '.$detalle : $origen;
    }
}
