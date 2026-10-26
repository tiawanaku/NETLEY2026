<?php

namespace App\Filament\Admin\Support;

use App\Rules\ContactoUnico;
use App\Support\CodigosTelefonicos;
use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Reglas de Filament (para ->rules([...])) que impiden repetir el teléfono,
 * WhatsApp o correo de OTRO registro de la misma entidad. Arman
 * App\Rules\ContactoUnico con el estado del formulario: el código de país
 * del selector, el registro editado (se ignora) y una restricción extra
 * opcional que recibe el Get del formulario (ej. el wizard de Cliente
 * Ejecutivo excluye al cliente con la misma CI, que se actualiza en vez de
 * duplicarse).
 *
 * Teléfono/WhatsApp se usan desde CamposTelefono::make(unicoEn: ...).
 */
class ReglasContacto
{
    /**
     * @param  class-string<Model>  $modelo
     * @param  (Closure(Builder, Get): mixed)|null  $alcance
     */
    public static function telefono(string $modelo, string $campoCodigo, string $entidad, ?Closure $alcance = null, ?string $mensaje = null): Closure
    {
        return fn (Get $get, ?Model $record): ContactoUnico => new ContactoUnico(
            modelo: $modelo,
            tipo: ContactoUnico::TELEFONO,
            codigo: $get($campoCodigo) ?: CodigosTelefonicos::POR_DEFECTO,
            ignorar: $record,
            alcance: self::alcance($alcance, $get),
            entidad: $entidad,
            mensaje: $mensaje,
        );
    }

    /**
     * @param  class-string<Model>  $modelo
     * @param  (Closure(Builder, Get): mixed)|null  $alcance
     */
    public static function correo(string $modelo, string $entidad, ?Closure $alcance = null): Closure
    {
        return fn (Get $get, ?Model $record): ContactoUnico => new ContactoUnico(
            modelo: $modelo,
            tipo: ContactoUnico::CORREO,
            ignorar: $record,
            alcance: self::alcance($alcance, $get),
            entidad: $entidad,
        );
    }

    /**
     * Wizard de Cliente Ejecutivo: crear() hace updateOrCreate por CI, así
     * que el cliente con la misma CI es la misma persona (se actualiza) y no
     * cuenta como "otro cliente".
     *
     * @return Closure(Builder, Get): void
     */
    public static function excluirMismaCi(): Closure
    {
        return function (Builder $query, Get $get): void {
            $ci = trim((string) $get('ci'));

            if ($ci !== '') {
                $query->where(fn (Builder $q) => $q->whereNull('ci')->orWhere('ci', '!=', $ci));
            }
        };
    }

    private static function alcance(?Closure $alcance, Get $get): ?Closure
    {
        return $alcance === null ? null : fn (Builder $query) => $alcance($query, $get);
    }
}
