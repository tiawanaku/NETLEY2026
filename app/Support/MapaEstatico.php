<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imagen fija (PNG) de un mapa con el marcador del domicilio, para los PDF
 * (DomPDF no ejecuta JavaScript, así que el mapa Leaflet del formulario no
 * sirve ahí). Se arma con las mismas cuadrículas de OpenStreetMap que usa el
 * mapa del formulario, unidas con GD; cada cuadrícula se guarda en
 * storage/app/mapa-tiles para no volver a pedirla.
 *
 * Si no hay internet (o falla OSM) devuelve null y el PDF muestra solo las
 * coordenadas.
 */
class MapaEstatico
{
    private const TAM_TILE = 256;

    /** @return string|null PNG en base64, listo para <img src="data:image/png;base64,..."> */
    public static function base64(float $lat, float $lng, int $zoom = 16, int $ancho = 640, int $alto = 300): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        try {
            // Posición del punto en píxeles "del mundo" a este zoom (Web Mercator).
            $n = 2 ** $zoom;
            $x = ($lng + 180) / 360 * $n * self::TAM_TILE;
            $latRad = deg2rad($lat);
            $y = (1 - log(tan($latRad) + 1 / cos($latRad)) / M_PI) / 2 * $n * self::TAM_TILE;

            $origenX = $x - $ancho / 2;
            $origenY = $y - $alto / 2;

            $img = imagecreatetruecolor($ancho, $alto);
            imagefill($img, 0, 0, imagecolorallocate($img, 229, 231, 235));

            $tileX0 = (int) floor($origenX / self::TAM_TILE);
            $tileY0 = (int) floor($origenY / self::TAM_TILE);
            $tileX1 = (int) floor(($origenX + $ancho - 1) / self::TAM_TILE);
            $tileY1 = (int) floor(($origenY + $alto - 1) / self::TAM_TILE);
            $alguna = false;

            for ($tx = $tileX0; $tx <= $tileX1; $tx++) {
                for ($ty = $tileY0; $ty <= $tileY1; $ty++) {
                    if ($ty < 0 || $ty >= $n) {
                        continue;
                    }

                    $png = self::tile($zoom, (($tx % $n) + $n) % $n, $ty);
                    $tile = $png ? @imagecreatefromstring($png) : false;

                    if (! $tile) {
                        continue;
                    }

                    imagecopy($img, $tile,
                        (int) round($tx * self::TAM_TILE - $origenX),
                        (int) round($ty * self::TAM_TILE - $origenY),
                        0, 0, self::TAM_TILE, self::TAM_TILE);
                    imagedestroy($tile);
                    $alguna = true;
                }
            }

            if (! $alguna) {
                imagedestroy($img);

                return null;
            }

            self::marcador($img, (int) round($ancho / 2), (int) round($alto / 2));

            // Atribución exigida por OpenStreetMap.
            $texto = '(c) OpenStreetMap';
            $anchoTexto = imagefontwidth(2) * strlen($texto);
            imagefilledrectangle($img, $ancho - $anchoTexto - 8, $alto - 16, $ancho, $alto, imagecolorallocatealpha($img, 255, 255, 255, 30));
            imagestring($img, 2, $ancho - $anchoTexto - 4, $alto - 15, $texto, imagecolorallocate($img, 55, 65, 81));

            ob_start();
            imagepng($img);
            $datos = ob_get_clean();
            imagedestroy($img);

            return base64_encode($datos);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Una cuadrícula de OSM (en caché local). */
    private static function tile(int $z, int $x, int $y): ?string
    {
        $disco = Storage::disk('local');
        $ruta = "mapa-tiles/{$z}/{$x}/{$y}.png";

        if ($disco->exists($ruta)) {
            return $disco->get($ruta);
        }

        $respuesta = Http::timeout(6)
            // En Windows el PHP suele venir sin lista de certificados: se usa
            // la del sistema (sigue verificando SSL). En Linux no hace falta.
            ->when(PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA'),
                fn ($http) => $http->withOptions(['curl' => [CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA]]))
            ->withHeaders(['User-Agent' => 'NETLEY-admin/1.0 (impresion de ficha)'])
            ->get("https://tile.openstreetmap.org/{$z}/{$x}/{$y}.png");

        if (! $respuesta->successful()) {
            return null;
        }

        $disco->put($ruta, $respuesta->body());

        return $respuesta->body();
    }

    /** Pin rojo con borde blanco; la punta queda justo en ($cx, $cy). */
    private static function marcador(\GdImage $img, int $cx, int $cy): void
    {
        imageantialias($img, true);
        $blanco = imagecolorallocate($img, 255, 255, 255);
        $rojo = imagecolorallocate($img, 220, 38, 38);

        // Borde blanco (un poco más grande) y luego el rojo encima.
        foreach ([[$blanco, 3], [$rojo, 0]] as [$color, $extra]) {
            imagefilledellipse($img, $cx, $cy - 22, 24 + $extra * 2, 24 + $extra * 2, $color);
            imagefilledpolygon($img, [
                $cx - 10 - $extra, $cy - 17,
                $cx + 10 + $extra, $cy - 17,
                $cx, $cy + $extra,
            ], $color);
        }

        imagefilledellipse($img, $cx, $cy - 22, 9, 9, $blanco);
    }
}
