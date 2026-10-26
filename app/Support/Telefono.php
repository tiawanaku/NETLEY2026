<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Teléfonos del sistema. Formato de guardado (columna existente, sin
 * migraciones): código de país + espacio + número, ej. "+591 71234567" o
 * "+1 3055551234" (ver componer()). Los datos anteriores se guardaban solo
 * con el número (el formulario mostraba "+591" fijo), así que un número
 * sin "+"/"00" se sigue tomando como boliviano al mostrarlo o editarlo; al
 * volver a guardarlo desde el formulario queda en el formato nuevo.
 *
 * libphonenumber (giggsey/libphonenumber-for-php-lite, sin llamadas
 * externas) se usa para armar el enlace de WhatsApp en formato E.164.
 */
class Telefono
{
    public const REGION_POR_DEFECTO = 'BO';

    /**
     * Separa un teléfono guardado en código de país y número (solo dígitos)
     * para el formulario. Un texto libre antiguo que no es un número (ej.
     * "22441234 int. 105") se devuelve tal cual en "numero" para que se vea
     * y se corrija al editar.
     *
     * @return array{codigo: string, numero: ?string}
     */
    public static function descomponer(?string $valor): array
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return ['codigo' => CodigosTelefonicos::POR_DEFECTO, 'numero' => null];
        }

        $compacto = preg_replace('/[\s\-.()\/]/', '', $valor);

        if (str_starts_with($compacto, '00')) {
            $compacto = '+'.substr($compacto, 2);
        }

        if (preg_match('/^\+(\d+)$/', $compacto, $m)) {
            $codigo = CodigosTelefonicos::codigoDe($m[1]) ?? self::codigoSegunLibphonenumber($compacto);

            if ($codigo !== null) {
                return ['codigo' => $codigo, 'numero' => substr($m[1], strlen($codigo) - 1)];
            }
        }

        if (preg_match('/^\d+$/', $compacto)) {
            return ['codigo' => CodigosTelefonicos::POR_DEFECTO, 'numero' => $compacto];
        }

        return ['codigo' => CodigosTelefonicos::POR_DEFECTO, 'numero' => $valor];
    }

    /** Une código y número en el formato de guardado: "+591 71234567". */
    public static function componer(?string $codigo, ?string $numero): ?string
    {
        $numero = trim((string) $numero);

        if ($numero === '') {
            return null;
        }

        return (filled($codigo) ? $codigo : CodigosTelefonicos::POR_DEFECTO).' '.$numero;
    }

    /**
     * Texto para mostrar (tablas, PDFs, vistas): "+591 71234567", sin
     * bandera. Un dato antiguo sin código se muestra con +591; un texto
     * libre que no es un número se muestra tal cual.
     */
    public static function formatear(?string $valor): ?string
    {
        $partes = self::descomponer($valor);

        if ($partes['numero'] === null) {
            return null;
        }

        return preg_match('/^\d+$/', $partes['numero'])
            ? $partes['codigo'].' '.$partes['numero']
            : $partes['numero'];
    }

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
            'nacional' => $util->format($telefono, PhoneNumberFormat::NATIONAL),
        ];
    }

    /** Enlace oficial "click to chat" de WhatsApp (https://wa.me/<número>). */
    public static function urlWhatsapp(?string $numero): ?string
    {
        $datos = self::analizar($numero);

        return $datos ? 'https://wa.me/'.ltrim($datos['e164'], '+') : null;
    }

    /** Código de país ("+NN") que libphonenumber reconoce en un número con "+". */
    private static function codigoSegunLibphonenumber(string $numero): ?string
    {
        try {
            $codigo = PhoneNumberUtil::getInstance()->parse($numero)->getCountryCode();
        } catch (NumberParseException) {
            return null;
        }

        return $codigo ? '+'.$codigo : null;
    }
}
