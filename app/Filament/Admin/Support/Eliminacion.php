<?php

namespace App\Filament\Admin\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Campos que se piden al eliminar un caso o un cliente — fecha, motivo y un
 * documento de respaldo — y cómo se guardan antes del borrado lógico. Al
 * restaurar, se limpian (ver Caso::booted()).
 */
class Eliminacion
{
    public const DIRECTORIO = 'documentos-eliminacion';

    /** @return array<int, \Filament\Forms\Components\Field> */
    public static function campos(): array
    {
        return [
            // Arranca en la fecha de hoy, pero se puede cambiar.
            DatePicker::make('fecha_eliminacion')
                ->label('Fecha')
                ->default(now())
                ->required(),
            Textarea::make('motivo_eliminacion')
                ->label('Motivo')
                ->placeholder('¿Por qué se elimina?')
                ->required()
                ->minLength(5)
                ->rows(3),
            FileUpload::make('documento_eliminacion')
                ->label('Documento')
                ->helperText('Documento de respaldo de la eliminación (opcional).')
                ->directory(self::DIRECTORIO)
                ->maxSize(20480),
        ];
    }

    /** Valores iniciales para fillForm() (los defaults de los campos no se aplican ahí). */
    public static function valoresIniciales(): array
    {
        return ['fecha_eliminacion' => now()->toDateString(), 'motivo_eliminacion' => null, 'documento_eliminacion' => null];
    }

    /** Guarda fecha, motivo y documento, y elimina (borrado lógico). */
    public static function eliminar(Model $registro, array $data): void
    {
        $documento = $data['documento_eliminacion'] ?? null;

        if (is_array($documento)) {
            $documento = collect($documento)->first();
        }

        DB::transaction(function () use ($registro, $data, $documento): void {
            $registro->forceFill([
                'fecha_eliminacion' => $data['fecha_eliminacion'],
                'motivo_eliminacion' => trim((string) $data['motivo_eliminacion']),
                'documento_eliminacion' => filled($documento) ? (string) $documento : null,
            ])->saveQuietly();

            $registro->delete();
        });
    }

    /**
     * Nombre del documento de respaldo, o "—". El disco es privado: se
     * descarga con el botón "Descargar documento" (ver descargar()).
     */
    public static function enlaceDocumento(?string $ruta): HtmlString
    {
        return new HtmlString('<strong>'.e(filled($ruta) ? basename($ruta) : '—').'</strong>');
    }

    /** Descarga el documento de respaldo de la eliminación. */
    public static function descargar(?string $ruta, ?string $nombre = null): mixed
    {
        $disk = Storage::disk(config('filament.default_filesystem_disk'));

        abort_unless(filled($ruta) && $disk->exists($ruta), 404);

        $extension = pathinfo($ruta, PATHINFO_EXTENSION);

        return response()->streamDownload(
            fn () => print ($disk->get($ruta)),
            $nombre ? $nombre.($extension ? '.'.$extension : '') : basename($ruta),
        );
    }
}
