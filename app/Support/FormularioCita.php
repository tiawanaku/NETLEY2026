<?php

namespace App\Support;

use App\Models\Cita;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "Formulario N": PDF de la ficha de agendamiento (pdf.ficha-agendamiento)
 * tal como está en ese momento, guardado en los documentos de la cita. Se
 * genera al crear cada cita y al guardar cada edición de la ficha; la
 * numeración sigue la de los formularios que la cita ya tenga (al
 * reagendar, la cita nueva hereda los documentos de la anterior).
 */
class FormularioCita
{
    public static function generar(Cita $cita): void
    {
        $consulta = $cita->consulta;

        if (! $consulta) {
            return;
        }

        $documentos = $cita->documentos ?? [];
        $numero = (collect($documentos)->map(fn ($documento) => self::numero($documento) ?? 0)->max() ?? 0) + 1;

        $pdf = Pdf::loadView('pdf.ficha-agendamiento', [
            'consulta' => $consulta,
            'citas' => $consulta->citas()->with('personal')->orderBy('fecha')->orderBy('hora')->get(),
        ])->output();

        $ruta = 'documentos-citas/formulario-'.$consulta->id.'-'.$numero.'-'.Str::random(8).'.pdf';
        Storage::disk(config('filament.default_filesystem_disk'))->put($ruta, $pdf);

        $documentos[] = ['ruta' => $ruta, 'descripcion' => "Formulario {$numero}"];

        $cita->update(['documentos' => self::ordenar($documentos)]);
    }

    /**
     * Los formularios primero (Formulario 1, 2, ...) y después el resto de
     * documentos en el orden en que se subieron.
     *
     * @param  array<int, array<string, mixed>>  $documentos
     * @return array<int, array<string, mixed>>
     */
    public static function ordenar(array $documentos): array
    {
        [$formularios, $otros] = collect($documentos)->partition(fn ($documento) => self::numero($documento) !== null);

        return $formularios->sortBy(fn ($documento) => self::numero($documento))->values()
            ->concat($otros->values())
            ->all();
    }

    /** @param  array<string, mixed>  $documento */
    protected static function numero(array $documento): ?int
    {
        return preg_match('/^Formulario (\d+)$/', (string) ($documento['descripcion'] ?? ''), $coincidencia)
            ? (int) $coincidencia[1]
            : null;
    }
}
