<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige texto con doble codificación (UTF-8 que en algún momento del
 * import legacy se leyó como Windows-1252 y se volvió a guardar como
 * UTF-8, ej. "ACCESIÃ“N" en vez de "ACCESIÓN"). Revisa todas las columnas
 * de texto de las tablas indicadas y arregla solo las celdas que
 * contienen el patrón de corrupción ("Ã" o "Â").
 *
 * Uso: php artisan texto:corregir-codificacion [--dry-run]
 */
class CorregirCodificacionTexto extends Command
{
    protected $signature = 'texto:corregir-codificacion {--dry-run : Solo muestra qué cambiaría, no guarda nada}';

    protected $description = 'Corrige texto con doble codificación UTF-8/Windows-1252 heredado del import legacy';

    /** @var array<int, string> */
    protected array $tablas = [
        'consultas', 'personal', 'clientes', 'casos',
        'citas', 'respuestas', 'llamadas', 'delitos',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $totalCorregidas = 0;
        $totalFallidas = 0;

        DB::transaction(function () use ($dryRun, &$totalCorregidas, &$totalFallidas): void {
            foreach ($this->tablas as $tabla) {
                if (! Schema::hasTable($tabla)) {
                    continue;
                }

                $columnas = Schema::getColumnListing($tabla);
                $filas = DB::table($tabla)->get();

                foreach ($filas as $fila) {
                    $cambios = [];

                    foreach ($columnas as $columna) {
                        $valor = $fila->{$columna} ?? null;

                        if (! is_string($valor) || (! str_contains($valor, 'Ã') && ! str_contains($valor, 'Â'))) {
                            continue;
                        }

                        $arreglado = mb_convert_encoding($valor, 'Windows-1252', 'UTF-8');

                        if (! mb_check_encoding($arreglado, 'UTF-8') || str_contains($arreglado, 'Ã') || str_contains($arreglado, '?')) {
                            $this->error("No se pudo corregir {$tabla}.{$columna} (id {$fila->id}): [{$valor}]");
                            $totalFallidas++;

                            continue;
                        }

                        $cambios[$columna] = $arreglado;
                        $totalCorregidas++;
                    }

                    if ($cambios === []) {
                        continue;
                    }

                    $this->line("{$tabla} (id {$fila->id}): ".implode(', ', array_keys($cambios)));

                    if (! $dryRun) {
                        DB::table($tabla)->where('id', $fila->id)->update($cambios);
                    }
                }
            }
        });

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '')."Celdas corregidas: {$totalCorregidas}. Fallidas: {$totalFallidas}.");

        return $totalFallidas > 0 ? self::FAILURE : self::SUCCESS;
    }
}
