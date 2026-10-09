<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Interpreta un teléfono guardado en el sistema con libphonenumber
 * (giggsey/libphonenumber-for-php-lite, sin llamadas externas). Los números
 * se guardan sin código de país y el formulario muestra "+591" fijo, así que
 * un número sin "+"/"00" se toma como boliviano; si trae código propio
 * (ej. "+54 9 11…", "0054…"), se respeta.
 */
class Telefono
{
    public const REGION_POR_DEFECTO = 'BO';

    /**
     * @return array{region: ?string, codigo: int, e164: string, internacional: string, nacional: string}|null
     */
    public static function analizar(?string $numero): ?array
    {
        $numero = trim((string) $numero);

        if ($numero === '') {
            return null;
        }

        if (str_starts_with($numero, '00')) {
            $numero = '+'.substr($numero, 2);
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $telefono = $util->parse($numero, self::REGION_POR_DEFECTO);
        } catch (NumberParseException) {
            return null;
        }

        $region = $util->getRegionCodeForNumber($telefono);

        return [
            'region' => in_array($region, [null, 'ZZ', '001'], true) ? null : $region,
            'codigo' => (int) $telefono->getCountryCode(),
            'e164' => $util->format($telefono, PhoneNumberFormat::E164),
            'internacional' => $util->format($telefono, PhoneNumberFormat::INTERNATIONAL),
            // Sin el código de país: la bandera ya lo indica, no hace falta
            // repetirlo al lado en la columna de la tabla.
            'nacional' => $util->format($telefono, PhoneNumberFormat::NATIONAL),
        ];
    }

    /** Enlace oficial "click to chat" de WhatsApp (https://wa.me/<número>). */
    public static function urlWhatsapp(?string $numero): ?string
    {
        $datos = self::analizar($numero);

        return $datos ? 'https://wa.me/'.ltrim($datos['e164'], '+') : null;
    }
}
