<?php

namespace App\Filament\Admin\Support;

use App\Support\CodigosTelefonicos;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtros de texto "Teléfono", "WhatsApp" y "Correo" para las tablas del
 * panel. Buscan por coincidencia parcial; los de teléfono/WhatsApp
 * comparan solo los dígitos, así que encuentran el número con o sin código
 * de país y con o sin espacios/guiones, tanto en el formato actual
 * ("+591 71234567", ver App\Support\Telefono) como en los datos legados
 * guardados solo con el número (que se toman como bolivianos, +591).
 *
 * Con $relacion el filtro busca en un modelo relacionado (ej. el cliente
 * de un caso); con varias relaciones basta que coincida una.
 */
class FiltrosContacto
{
    /** Caracteres que se ignoran al comparar teléfonos. */
    private const SEPARADORES = [' ', '-', '+', '(', ')', '.', '/'];

    /**
     * Los tres filtros (o los indicados en $tipos) sobre las columnas
     * estándar telefono / whatsapp / correo.
     *
     * @param  array<int, 'telefono'|'whatsapp'|'correo'>  $tipos
     * @param  string|array<int, string>|null  $relacion
     * @return array<int, Filter>
     */
    public static function make(array $tipos = ['telefono', 'whatsapp', 'correo'], string|array|null $relacion = null): array
    {
        return array_map(fn (string $tipo): Filter => match ($tipo) {
            'telefono' => self::telefono(relacion: $relacion),
            'whatsapp' => self::whatsapp(relacion: $relacion),
            'correo' => self::correo(relacion: $relacion),
        }, $tipos);
    }

    /**
     * @param  string|array<int, string>  $columnas  Varias columnas: basta que coincida una.
     * @param  string|array<int, string>|null  $relacion
     */
    public static function telefono(
        string|array $columnas = 'telefono',
        string|array|null $relacion = null,
        string $nombre = 'telefono',
        string $etiqueta = 'Teléfono',
    ): Filter {
        return self::filtro($nombre, $etiqueta, 'Ej. 71234567 o +591 71234567', $relacion, function (Builder $query, string $valor) use ($columnas): void {
            self::condicionTelefono($query, (array) $columnas, $valor);
        }, telefono: true);
    }

    /**
     * @param  string|array<int, string>|null  $relacion
     */
    public static function whatsapp(string|array|null $relacion = null, string $columna = 'whatsapp'): Filter
    {
        return self::telefono($columna, $relacion, 'whatsapp', 'WhatsApp');
    }

    /**
     * @param  string|array<int, string>|null  $relacion
     */
    public static function correo(string|array|null $relacion = null, string $columna = 'correo'): Filter
    {
        return self::filtro('correo', 'Correo', 'Ej. juan@gmail.com', $relacion, function (Builder $query, string $valor) use ($columna): void {
            $escapado = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);

            $query->where($query->qualifyColumn($columna), 'like', '%'.$escapado.'%');
        });
    }

    /**
     * Qué buscar a partir de lo que escribió el usuario: los dígitos y, si
     * escribió un código de país (con "+"/"00", o 591 seguido del número),
     * ese código y el número sin él.
     *
     * @return array{digitos: string, codigo: ?string, numero: ?string}|null
     */
    public static function busquedaTelefono(?string $texto): ?array
    {
        $compacto = preg_replace('/[^\d+]/', '', trim((string) $texto));

        if (str_starts_with($compacto, '00')) {
            $compacto = '+'.substr($compacto, 2);
        }

        $digitos = preg_replace('/\D/', '', $compacto);

        if ($digitos === '') {
            return null;
        }

        $porDefecto = ltrim(CodigosTelefonicos::POR_DEFECTO, '+');
        $codigo = null;

        if (str_starts_with($compacto, '+')) {
            $codigo = CodigosTelefonicos::codigoDe($digitos);
        } elseif (strlen($digitos) >= strlen($porDefecto) + 7 && str_starts_with($digitos, $porDefecto)) {
            // "591 71234567" escrito sin "+".
            $codigo = CodigosTelefonicos::POR_DEFECTO;
        }

        return [
            'digitos' => $digitos,
            'codigo' => $codigo,
            'numero' => $codigo !== null ? substr($digitos, strlen($codigo) - 1) : null,
        ];
    }

    /**
     * @param  array<int, string>  $columnas
     */
    private static function condicionTelefono(Builder $query, array $columnas, string $valor): void
    {
        $busqueda = self::busquedaTelefono($valor);

        // Sin dígitos (ej. "int."): búsqueda de texto simple, para datos
        // legados escritos a mano.
        if ($busqueda === null) {
            $escapado = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);

            $query->where(function (Builder $query) use ($columnas, $escapado): void {
                foreach ($columnas as $columna) {
                    $query->orWhere($query->qualifyColumn($columna), 'like', '%'.$escapado.'%');
                }
            });

            return;
        }

        $query->where(function (Builder $query) use ($columnas, $busqueda): void {
            foreach ($columnas as $columna) {
                $columnaSql = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn($columna));
                $soloDigitos = self::sinSeparadores($columnaSql);

                $query->orWhere(function (Builder $query) use ($columnaSql, $soloDigitos, $busqueda): void {
                    // Formato actual ("+591 71234567") o cualquier dato que
                    // contenga esos dígitos.
                    $query->whereRaw("{$soloDigitos} like ?", ['%'.$busqueda['digitos'].'%']);

                    // Dato legado sin código (= boliviano) buscado con +591.
                    if ($busqueda['codigo'] === CodigosTelefonicos::POR_DEFECTO) {
                        $query->orWhere(function (Builder $query) use ($columnaSql, $soloDigitos, $busqueda): void {
                            $query->whereRaw("{$columnaSql} not like ?", ['+%'])
                                ->whereRaw("{$columnaSql} not like ?", ['00%'])
                                ->whereRaw("{$soloDigitos} like ?", ['%'.$busqueda['numero'].'%']);
                        });
                    }
                });
            }
        });
    }

    private static function sinSeparadores(string $columnaSql): string
    {
        $sql = "coalesce({$columnaSql}, '')";

        foreach (self::SEPARADORES as $separador) {
            $sql = "replace({$sql}, '{$separador}', '')";
        }

        return $sql;
    }

    /**
     * @param  string|array<int, string>|null  $relacion
     * @param  Closure(Builder, string): void  $condicion
     */
    private static function filtro(
        string $nombre,
        string $etiqueta,
        string $placeholder,
        string|array|null $relacion,
        Closure $condicion,
        bool $telefono = false,
    ): Filter {
        $campo = TextInput::make('valor')
            ->label($etiqueta)
            ->placeholder($placeholder)
            ->maxLength(120)
            ->autocomplete(false);

        if ($telefono) {
            // Solo el teclado numérico en el celular; sin la validación de
            // ->tel(), que rechazaría búsquedas parciales.
            $campo->extraInputAttributes(['inputmode' => 'tel']);
        }

        return Filter::make($nombre)
            ->label($etiqueta)
            ->schema([$campo])
            ->query(function (Builder $query, array $data) use ($relacion, $condicion): Builder {
                $valor = trim((string) ($data['valor'] ?? ''));

                if ($valor === '') {
                    return $query;
                }

                $relaciones = array_values(array_filter((array) $relacion));

                if ($relaciones === []) {
                    return $query->where(fn (Builder $query) => $condicion($query, $valor));
                }

                return $query->where(function (Builder $query) use ($relaciones, $condicion, $valor): void {
                    foreach ($relaciones as $nombreRelacion) {
                        $query->orWhereHas($nombreRelacion, fn (Builder $query) => $condicion($query, $valor));
                    }
                });
            })
            ->indicateUsing(fn (array $data): ?string => filled($data['valor'] ?? null)
                ? $etiqueta.': '.trim((string) $data['valor'])
                : null);
    }
}
