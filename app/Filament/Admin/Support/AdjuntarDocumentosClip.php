<?php

namespace App\Filament\Admin\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Campo "clip" para agregar filas a un Repeater de documentos en un solo
 * clic: a diferencia del botón "agregar" del Repeater (que recién crea el
 * FileUpload de esa fila DESPUÉS del clic, cuando ya es tarde para abrirlo
 * solo — los navegadores no dejan abrir el explorador de archivos por
 * JavaScript sobre un campo que no existía en el momento exacto del clic),
 * este campo YA está presente en el formulario desde que se abre, así que
 * un clic real sobre él (reducido visualmente a un ícono de clip por CSS,
 * ver .netley-adjuntar-documentos-clip en admin-theme.css) abre el
 * explorador de una vez, de forma nativa, sin trucos.
 *
 * Al elegir archivo(s), los empuja como fila(s) nueva(s) del Repeater
 * indicado y se vacía para el próximo uso. Nunca se guarda él mismo.
 *
 * El Repeater que lo use debe ir con ->addable(false): este campo
 * reemplaza su botón de agregar.
 */
class AdjuntarDocumentosClip
{
    public static function make(string $repeaterField, string $directory, string $rutaField = 'ruta'): FileUpload
    {
        return FileUpload::make($repeaterField.'Clip')
            ->hiddenLabel()
            ->directory($directory)
            ->multiple()
            ->live()
            ->dehydrated(false)
            ->extraAttributes(['class' => 'netley-adjuntar-documentos-clip'])
            ->afterStateUpdated(function ($state, Set $set, Get $get) use ($repeaterField, $rutaField): void {
                if (blank($state)) {
                    return;
                }

                $items = $get($repeaterField) ?? [];

                foreach ((array) $state as $archivo) {
                    // El valor de un FileUpload (incluso de uno solo, como
                    // 'ruta' acá) se guarda internamente como un array con
                    // clave UUID (ver Filament\Schemas\Components\StateCasts\FileUploadStateCast) —
                    // al escribir el estado directo por PHP (en vez de por
                    // la interacción normal del campo) hay que armarlo igual,
                    // si no la validación de Filament falla al guardar.
                    $items[(string) Str::uuid()] = [$rutaField => [(string) Str::uuid() => $archivo]];
                }

                $set($repeaterField, $items);
                $set($repeaterField.'Clip', null);
            });
    }

    /**
     * Nombre del archivo a mostrar junto a la descripción de cada fila: el
     * original elegido por la persona si todavía es un archivo recién
     * subido (no guardado), o el nombre del archivo ya guardado en disco.
     */
    public static function nombreArchivo(mixed $ruta): ?string
    {
        // Por si $get() devuelve el array interno (uuid => archivo) en vez
        // del valor ya "destapado" (ver FileUploadStateCast::get()).
        if (is_array($ruta)) {
            $ruta = \Illuminate\Support\Arr::first($ruta);
        }

        if ($ruta instanceof TemporaryUploadedFile) {
            return $ruta->getClientOriginalName();
        }

        if (is_string($ruta) && filled($ruta)) {
            return basename($ruta);
        }

        return null;
    }
}
