<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sugerencias de País / Provincia / Ciudad para el formulario de Consultas,
 * usando la API pública countriesnow.space. Los campos siguen siendo de
 * texto libre (->datalist()): si el dato real no está en esta API (común
 * para poblaciones pequeñas de Bolivia), el usuario puede igual escribirlo
 * a mano. Cada nivel se cachea por separado porque depende del anterior.
 *
 * Caso especial Bolivia: esta API solo conoce 2 niveles por país (country →
 * states → cities). Para Bolivia, lo que la API llama "states" son en
 * realidad los 9 DEPARTAMENTOS, no provincias — y las provincias reales
 * (ej. "Murillo", "Aroma") aparecen escondidas dentro de la lista de
 * "cities" de cada departamento, con el prefijo "Provincia " (ej.
 * "Provincia Murillo"), mezcladas con ciudades de verdad. Por eso el campo
 * "Provincia" de Bolivia se arma aparte, recorriendo los 9 departamentos y
 * extrayendo esas entradas con prefijo; y "Ciudad" primero ubica a qué
 * departamento pertenece la provincia elegida para pedir sus ciudades.
 */
class PaisesCiudades
{
    protected const BASE = 'https://countriesnow.space/api/v0.1';

    protected const TTL_DIAS = 30;

    protected const BOLIVIA = 'Bolivia';

    /**
     * @return array<int, string>
     */
    public static function paises(): array
    {
        return Cache::remember('paises_ciudades.paises', now()->addDays(self::TTL_DIAS), function () {
            try {
                // Importante: /countries/positions devuelve el nombre OFICIAL largo
                // ("Bolivia, Plurinational State of Bolivia"), que luego no coincide
                // con el nombre que esperan /countries/states y /countries/state/cities
                // ("Bolivia"). /countries sí usa ese mismo nombre corto en "country".
                $response = Http::timeout(5)->get(self::BASE.'/countries');

                if (! $response->successful()) {
                    return [];
                }

                return collect($response->json('data'))
                    ->pluck('country')
                    ->filter()
                    ->sort()
                    ->values()
                    ->all();
            } catch (Throwable) {
                return [];
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public static function provincias(?string $pais): array
    {
        if (blank($pais)) {
            return [];
        }

        if ($pais === self::BOLIVIA) {
            return array_keys(self::mapaProvinciasBolivia());
        }

        return self::departamentos($pais);
    }

    /**
     * @return array<int, string>
     */
    public static function ciudades(?string $pais, ?string $provincia): array
    {
        if (blank($pais) || blank($provincia)) {
            return [];
        }

        // En Bolivia, "provincia" (ej. "Murillo") no es lo que la API espera
        // como "state" — hay que traducirlo primero al departamento al que
        // pertenece (ej. "La Paz Department") para poder pedir sus ciudades.
        $departamento = $pais === self::BOLIVIA
            ? (self::mapaProvinciasBolivia()[$provincia] ?? null)
            : $provincia;

        if (blank($departamento)) {
            return [];
        }

        return self::ciudadesCrudas($pais, $departamento)
            ->reject(fn (string $ciudad) => str_starts_with(mb_strtolower($ciudad), 'provincia '))
            ->values()
            ->all();
    }

    /**
     * Departamentos/estados tal cual los da la API (para cualquier país que
     * no sea Bolivia, donde ese nivel sí corresponde a "provincia").
     *
     * @return array<int, string>
     */
    protected static function departamentos(string $pais): array
    {
        return Cache::remember(
            'paises_ciudades.departamentos.'.$pais,
            now()->addDays(self::TTL_DIAS),
            function () use ($pais) {
                try {
                    $response = Http::timeout(5)->post(self::BASE.'/countries/states', [
                        'country' => $pais,
                    ]);

                    if (! $response->successful()) {
                        return [];
                    }

                    return collect($response->json('data.states'))
                        ->pluck('name')
                        ->filter()
                        ->sort()
                        ->values()
                        ->all();
                } catch (Throwable) {
                    return [];
                }
            }
        );
    }

    /**
     * Mapa "provincia real" => "departamento al que pertenece", construido
     * recorriendo los 9 departamentos de Bolivia y quedándose con las
     * entradas de su lista de "cities" que empiezan con "Provincia ".
     *
     * @return array<string, string>
     */
    protected static function mapaProvinciasBolivia(): array
    {
        return Cache::remember(
            'paises_ciudades.mapa_provincias_bolivia',
            now()->addDays(self::TTL_DIAS),
            function () {
                $mapa = [];

                foreach (self::departamentos(self::BOLIVIA) as $departamento) {
                    foreach (self::ciudadesCrudas(self::BOLIVIA, $departamento) as $item) {
                        if (! str_starts_with(mb_strtolower($item), 'provincia ')) {
                            continue;
                        }

                        $nombreProvincia = trim(mb_substr($item, mb_strlen('Provincia ')));

                        if ($nombreProvincia !== '') {
                            $mapa[$nombreProvincia] = $departamento;
                        }
                    }
                }

                ksort($mapa);

                return $mapa;
            }
        );
    }

    /**
     * Lista cruda (sin filtrar) de "cities" de la API para un departamento —
     * incluye tanto ciudades reales como las entradas "Provincia X". La usan
     * tanto ciudades() (que filtra las "Provincia X") como
     * mapaProvinciasBolivia() (que se queda solo con esas).
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected static function ciudadesCrudas(string $pais, string $departamento): \Illuminate\Support\Collection
    {
        return Cache::remember(
            'paises_ciudades.ciudades_crudas.'.$pais.'.'.$departamento,
            now()->addDays(self::TTL_DIAS),
            function () use ($pais, $departamento) {
                try {
                    $response = Http::timeout(5)->post(self::BASE.'/countries/state/cities', [
                        'country' => $pais,
                        'state' => $departamento,
                    ]);

                    if (! $response->successful()) {
                        return collect();
                    }

                    return collect($response->json('data'))->filter()->values();
                } catch (Throwable) {
                    return collect();
                }
            }
        );
    }
}
