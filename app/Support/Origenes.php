<?php

namespace App\Support;

/**
 * Orígenes de una consulta o llamada (por dónde llegó el contacto). El
 * campo "origen" es texto libre con sugerencias (datos legados con otros
 * valores siguen siendo válidos). Con "Talleres" se pide además el detalle
 * del taller en "origen_detalle"; con cualquier otro origen ese detalle se
 * descarta al guardar (ver limpiarDetalle()). Con "Referido", ese mismo
 * campo guarda quién lo refirió ("Referido por").
 */
class Origenes
{
    public const TALLERES = 'Talleres';

    public const REFERIDO = 'Referido';

    /** Valor del select de Origen (Consultas) que habilita escribirlo a mano. */
    public const OTRO = 'otro';

    /** @var array<int, string> */
    public const SUGERENCIAS = [
        'Cliente antiguo',
        'Recomendación',
        self::REFERIDO,
        'Espontáneo',
        'Página Netley',
        'Facebook',
        'WhatsApp',
        'TikTok',
        self::TALLERES,
    ];

    /** "Talleres", "taller", "TALLER colegio…": cualquier origen que empiece por "taller". */
    public static function esTalleres(?string $origen): bool
    {
        return str_starts_with(mb_strtolower(trim((string) $origen)), 'taller');
    }

    public static function esReferido(?string $origen): bool
    {
        return mb_strtolower(trim((string) $origen)) === mb_strtolower(self::REFERIDO);
    }

    /** Talleres (qué taller) o Referido (referido por): orígenes con detalle. */
    public static function llevaDetalle(?string $origen): bool
    {
        return self::esTalleres($origen) || self::esReferido($origen);
    }

    /**
     * Detalle a guardar: solo con origen Talleres/Referido y no vacío; si no, null.
     */
    public static function limpiarDetalle(?string $origen, ?string $detalle): ?string
    {
        $detalle = trim((string) $detalle);

        return self::llevaDetalle($origen) && $detalle !== '' ? $detalle : null;
    }

    /** Texto para mostrar: "Talleres — Colegio San Calixto", "Referido por Ana Pérez" o solo el origen. */
    public static function texto(?string $origen, ?string $detalle): ?string
    {
        $origen = trim((string) $origen);
        $detalle = self::limpiarDetalle($origen, $detalle);

        if ($origen === '') {
            return null;
        }

        if ($detalle === null) {
            return $origen;
        }

        return self::esReferido($origen) ? $origen.' por '.$detalle : $origen.' — '.$detalle;
    }
}
