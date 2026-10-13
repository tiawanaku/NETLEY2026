<?php

namespace App\Support;

/**
 * Catálogo único de códigos telefónicos de país (sin banderas) que usan
 * todos los campos de teléfono/WhatsApp del sistema: el selector de los
 * formularios de Filament (App\Filament\Admin\Support\CamposTelefono) y el
 * <select> nativo del formulario de consulta de la portada pública.
 *
 * Bolivia va primero (es el valor por defecto), luego los países de
 * América, España y otros países frecuentes. La clave es el código con "+"
 * (los códigos de país de la UIT no son prefijo unos de otros, así que un
 * número completo se puede separar sin ambigüedad); el +1 lo comparten
 * Estados Unidos, Canadá y varios países del Caribe.
 *
 * Los nombres van cortos para que el código elegido entre completo junto al
 * número ("+1 (EE. UU. / Canadá)"); ALIAS agrega otras formas de buscar
 * el país en el selector ("Estados Unidos", "USA"…).
 */
class CodigosTelefonicos
{
    public const POR_DEFECTO = '+591';

    /** @var array<string, string> código => país */
    public const PAISES = [
        '+591' => 'Bolivia',
        // América
        '+54' => 'Argentina',
        '+55' => 'Brasil',
        '+1' => 'EE. UU. / Canadá',
        '+56' => 'Chile',
        '+57' => 'Colombia',
        '+506' => 'Costa Rica',
        '+53' => 'Cuba',
        '+593' => 'Ecuador',
        '+503' => 'El Salvador',
        '+502' => 'Guatemala',
        '+509' => 'Haití',
        '+504' => 'Honduras',
        '+52' => 'México',
        '+505' => 'Nicaragua',
        '+507' => 'Panamá',
        '+595' => 'Paraguay',
        '+51' => 'Perú',
        '+598' => 'Uruguay',
        '+58' => 'Venezuela',
        // España y otros países frecuentes
        '+34' => 'España',
        '+49' => 'Alemania',
        '+61' => 'Australia',
        '+32' => 'Bélgica',
        '+86' => 'China',
        '+82' => 'Corea del Sur',
        '+971' => 'Emiratos Árabes',
        '+33' => 'Francia',
        '+91' => 'India',
        '+972' => 'Israel',
        '+39' => 'Italia',
        '+81' => 'Japón',
        '+31' => 'Países Bajos',
        '+351' => 'Portugal',
        '+44' => 'Reino Unido',
        '+7' => 'Rusia',
        '+27' => 'Sudáfrica',
        '+41' => 'Suiza',
        '+90' => 'Turquía',
    ];

    /** @var array<string, string> código => otras palabras con las que se busca el país */
    public const ALIAS = [
        '+1' => 'Estados Unidos USA EEUU Norteamérica Puerto Rico República Dominicana',
        '+971' => 'Emiratos Árabes Unidos EAU Dubái',
        '+44' => 'Inglaterra UK Gran Bretaña Escocia',
        '+31' => 'Holanda',
        '+82' => 'Corea',
        '+7' => 'Rusia Kazajistán',
    ];

    /** @return list<string> */
    public static function codigos(): array
    {
        return array_keys(self::PAISES);
    }

    /**
     * Opciones para un select: "+591 (Bolivia)", "+1 (EE. UU. / Canadá)"…
     * Si $actual es un código que no está en el catálogo (ej. un dato
     * antiguo), se agrega al final para no perderlo al editar.
     *
     * @return array<string, string>
     */
    public static function opciones(?string $actual = null): array
    {
        $opciones = [];

        foreach (self::PAISES as $codigo => $pais) {
            $opciones[$codigo] = "{$codigo} ({$pais})";
        }

        if (filled($actual) && ! isset($opciones[$actual])) {
            $opciones[$actual] = $actual;
        }

        return $opciones;
    }

    /**
     * Búsqueda del selector: por país, alias o código ("bol", "usa", "591",
     * "+54"), sin distinguir mayúsculas ni tildes.
     *
     * @return array<string, string>
     */
    public static function buscar(?string $termino): array
    {
        $normalizar = fn (string $texto): string => str($texto)->ascii()->lower()->toString();
        $termino = $normalizar(trim((string) $termino));

        if ($termino === '') {
            return self::opciones();
        }

        return array_filter(
            self::opciones(),
            fn (string $etiqueta, string $codigo): bool => str_contains(
                $normalizar($etiqueta.' '.(self::ALIAS[$codigo] ?? '')),
                $termino,
            ),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** Código del catálogo con el que empiezan los dígitos dados (sin "+"), o null. */
    public static function codigoDe(string $digitos): ?string
    {
        foreach (self::codigos() as $codigo) {
            if (str_starts_with($digitos, ltrim($codigo, '+'))) {
                return $codigo;
            }
        }

        return null;
    }
}
