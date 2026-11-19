<?php

namespace App\Console\Commands;

use App\Models\Cita;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Vuelve a generar los "Formulario N" (PDF de la ficha de agendamiento, ver
 * App\Support\FormularioCita) ya guardados en los documentos de las citas,
 * con el diseño actual de pdf.ficha-agendamiento y los datos actuales de la
 * consulta. Cada archivo se reemplaza en la misma ruta (los enlaces siguen
 * funcionando) y antes se guarda una copia del original en
 * formularios-respaldo/AAAA-MM-DD_HHMMSS/.
 */
class RegenerarFormularios extends Command
{
    protected $signature = 'netley:regenerar-formularios {--sin-respaldo : No guardar copia de los PDF anteriores}';

    protected $description = 'Vuelve a generar los "Formulario N" de las citas con el diseño actual';

    public function handle(): int
    {
        $disco = Storage::disk(config('filament.default_filesystem_disk'));
        $carpetaRespaldo = 'formularios-respaldo/'.now()->format('Y-m-d_His');
        $hechas = [];
        $faltantes = 0;

        Cita::query()->whereNotNull('documentos')->with('consulta')->orderBy('id')->each(function (Cita $cita) use ($disco, $carpetaRespaldo, &$hechas, &$faltantes): void {
            if (! $cita->consulta) {
                return;
            }

            foreach ($cita->documentos ?? [] as $documento) {
                $ruta = $documento['ruta'] ?? null;

                // Solo los "Formulario N" (los demás documentos no se tocan) y
                // una vez por archivo (al reagendar, la cita nueva hereda los
                // documentos de la anterior).
                if (! preg_match('/^Formulario \d+$/', (string) ($documento['descripcion'] ?? '')) || blank($ruta) || isset($hechas[$ruta])) {
                    continue;
                }

                if (! $disco->exists($ruta)) {
                    $faltantes++;

                    continue;
                }

                if (! $this->option('sin-respaldo')) {
                    $disco->copy($ruta, $carpetaRespaldo.'/'.basename($ruta));
                }

                $consulta = $cita->consulta;
                $disco->put($ruta, Pdf::loadView('pdf.ficha-agendamiento', [
                    'consulta' => $consulta,
                    'citas' => $consulta->citas()->with('personal')->orderBy('fecha')->orderBy('hora')->get(),
                ])->output());

                $hechas[$ruta] = true;
                $this->line("Consulta {$consulta->id} · {$documento['descripcion']} · {$ruta}");
            }
        });

        $this->info(count($hechas).' formulario(s) regenerado(s).'.($faltantes ? " {$faltantes} no tenían archivo." : ''));

        if (count($hechas) && ! $this->option('sin-respaldo')) {
            $this->line("Copia de los anteriores en: {$carpetaRespaldo}");
        }

        return self::SUCCESS;
    }
}
