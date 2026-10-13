<?php

namespace App\Rules;

use App\Support\CodigosTelefonicos;
use App\Support\Telefono;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Impide registrar un teléfono, WhatsApp o correo que ya pertenece a OTRO
 * registro de la misma entidad (personal, clientes, consultas). No hay
 * índice único en la BD (los datos legados pueden tener duplicados): la
 * regla se valida al guardar desde los formularios.
 *
 * - Teléfono/WhatsApp: se compara solo por dígitos del número completo con
 *   código, así "+591 71234567", "+59171234567" y el dato legado
 *   "71234567" (sin código, se asume +591) son el mismo número. La
 *   comparación es cruzada: un número cuenta como registrado si coincide con
 *   el teléfono O el WhatsApp de otro registro. Que el teléfono y el WhatsApp
 *   del mismo registro sean iguales sí está permitido (solo se busca en
 *   otros registros).
 * - Correo: sin distinguir mayúsculas ni espacios.
 * - Valor vacío: no se valida.
 * - Al editar se ignora el propio registro y, si el dato no cambió respecto
 *   de lo guardado, no se valida (para no bloquear la edición de otros
 *   campos de un registro legado que ya estaba duplicado).
 */
class ContactoUnico implements ValidationRule
{
    public const TELEFONO = 'telefono';

    public const CORREO = 'correo';

    /** Columnas de teléfono que se comparan en forma cruzada. */
    public const COLUMNAS_TELEFONO = ['telefono', 'whatsapp'];

    /**
     * @param  class-string<Model>  $modelo
     * @param  string  $tipo  self::TELEFONO o self::CORREO
     * @param  string|null  $codigo  Código de país del número (ej. "+591"); null si el valor ya trae el código.
     * @param  Model|null  $ignorar  Registro editado (se excluye; si no cambió el dato, no se valida).
     * @param  (Closure(Builder): mixed)|null  $alcance  Restricción extra de la búsqueda (ej. excluir al cliente con la misma CI).
     * @param  string  $entidad  Cómo nombrar al dueño del dato en el mensaje: "otro personal", "otro cliente", "otra consulta".
     * @param  string|null  $mensaje  Mensaje fijo (ej. portada pública, sin revelar a quién pertenece).
     */
    public function __construct(
        protected string $modelo,
        protected string $tipo,
        protected ?string $codigo = null,
        protected ?Model $ignorar = null,
        protected ?Closure $alcance = null,
        protected string $entidad = 'otro registro',
        protected ?string $mensaje = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return;
        }

        $duplicado = $this->tipo === self::CORREO
            ? $this->buscarCorreo((string) $value)
            : $this->buscarTelefono((string) $value);

        if ($duplicado === null) {
            return;
        }

        $fail($this->mensaje ?? $this->mensajePara($attribute, $duplicado));
    }

    protected function buscarTelefono(string $valor): ?Model
    {
        $variantes = self::variantesTelefono($this->codigo, $valor);

        if ($variantes === []) {
            return null;
        }

        if ($this->sinCambios(fn (?string $guardado): bool => array_intersect(
            self::variantesTelefono(null, (string) $guardado),
            $variantes,
        ) !== [])) {
            return null;
        }

        $modelo = new $this->modelo;

        return $this->consulta()
            ->where(function (Builder $query) use ($variantes, $modelo): void {
                foreach (self::COLUMNAS_TELEFONO as $columna) {
                    $query->orWhereIn(DB::raw(self::soloDigitosSql($modelo->qualifyColumn($columna))), $variantes);
                }
            })
            ->first();
    }

    protected function buscarCorreo(string $valor): ?Model
    {
        $correo = self::normalizarCorreo($valor);

        if ($this->sinCambios(fn (?string $guardado): bool => self::normalizarCorreo((string) $guardado) === $correo)) {
            return null;
        }

        return $this->consulta()
            ->whereRaw('LOWER(TRIM('.$this->columnaSql('correo').')) = ?', [$correo])
            ->first();
    }

    /** El registro editado ya tenía este mismo dato guardado (en la columna validada). */
    protected function sinCambios(Closure $coincide): bool
    {
        if (! $this->esRegistroPropio()) {
            return false;
        }

        $columnas = $this->tipo === self::CORREO ? ['correo'] : self::COLUMNAS_TELEFONO;

        foreach ($columnas as $columna) {
            $guardado = $this->ignorar->getOriginal($columna);

            if (filled($guardado) && $coincide($guardado)) {
                return true;
            }
        }

        return false;
    }

    protected function consulta(): Builder
    {
        /** @var Builder $query */
        $query = $this->modelo::query();

        if ($this->esRegistroPropio()) {
            $query->whereKeyNot($this->ignorar->getKey());
        }

        if ($this->alcance !== null) {
            ($this->alcance)($query);
        }

        return $query;
    }

    protected function esRegistroPropio(): bool
    {
        return $this->ignorar instanceof $this->modelo && $this->ignorar->exists;
    }

    protected function columnaSql(string $columna): string
    {
        $modelo = new $this->modelo;

        return $modelo->getConnection()->getQueryGrammar()->wrap($modelo->qualifyColumn($columna));
    }

    protected function mensajePara(string $attribute, Model $duplicado): string
    {
        $campo = match (true) {
            $this->tipo === self::CORREO => 'Este correo electrónico',
            str_ends_with($attribute, 'whatsapp') => 'Este número de WhatsApp',
            default => 'Este teléfono',
        };

        $nombre = trim((string) ($duplicado->getAttribute('nombre_completo') ?? ''));

        return $campo.' ya está registrado para '.$this->entidad.($nombre !== '' ? ' ('.$nombre.')' : '').'.';
    }

    /**
     * Formas en "solo dígitos" con las que puede estar guardado el número:
     * completo con código ("59171234567", también "0059171234567") y, si es
     * boliviano, solo el número (dato legado "71234567").
     *
     * @return array<int, string>
     */
    public static function variantesTelefono(?string $codigo, string $numero): array
    {
        $numero = trim($numero);

        if ($numero === '') {
            return [];
        }

        $partes = $codigo !== null
            ? ['codigo' => $codigo, 'numero' => $numero]
            : Telefono::descomponer($numero);

        $digitosCodigo = preg_replace('/\D/', '', (string) $partes['codigo']);
        $digitosNumero = preg_replace('/\D/', '', (string) $partes['numero']);

        if ($digitosNumero === '') {
            return [];
        }

        $completo = $digitosCodigo.$digitosNumero;
        $variantes = [$completo, '00'.$completo];

        if ($partes['codigo'] === CodigosTelefonicos::POR_DEFECTO) {
            $variantes[] = $digitosNumero;
        }

        return array_values(array_unique($variantes));
    }

    public static function normalizarCorreo(string $correo): string
    {
        return mb_strtolower(preg_replace('/\s+/u', '', $correo));
    }

    /**
     * Expresión SQL que deja solo los dígitos de una columna (quita espacios,
     * "+", guiones, puntos, paréntesis y "/"), portable entre MySQL/MariaDB
     * y SQLite. La columna es un nombre interno, nunca entrada del usuario.
     */
    protected static function soloDigitosSql(string $columna): string
    {
        $sql = DB::connection()->getQueryGrammar()->wrap($columna);

        foreach ([' ', '+', '-', '.', '(', ')', '/'] as $caracter) {
            $sql = "REPLACE({$sql}, '{$caracter}', '')";
        }

        return $sql;
    }
}
