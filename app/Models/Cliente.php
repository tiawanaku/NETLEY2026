<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Además de ser el registro de un cliente del despacho, este modelo es
 * Authenticatable para el guard `cliente`: así el cliente entra al portal
 * (resources/views/portal/*) con su propio usuario/contraseña, sin depender
 * de la tabla `users` (esa es solo para el personal/panel Filament).
 */
class Cliente extends Model implements AuthenticatableContract
{
    use Authenticatable, HasFactory;

    // Eliminar no lo borra de la base: ver booted().
    use SoftDeletes;

    protected $fillable = [
        'nombres',
        'ap_paterno',
        'ap_materno',
        'telefono',
        'whatsapp',
        'correo',
        'ci',
        'username',
        'password',
        'fecha_nacimiento',
        'extension',
        'sucursal',
        'direccion',
        'pais',
        'provincia',
        'ciudad',
        'zona',
        'calles',
        'numero_domicilio',
        'indicaciones_domicilio',
        'ubicacion',
        'nro_casos',
        'fecha_eliminacion',
        'motivo_eliminacion',
        'documento_eliminacion',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'password' => 'hashed',
            'ubicacion' => 'array',
            'fecha_eliminacion' => 'date',
        ];
    }

    /**
     * N° de cliente correlativo (1, 2, 3…), asignado al darlo de alta por
     * cualquier vía (wizard, importación legacy, etc.). A diferencia del id
     * interno, no salta números por guardados que fallan y se revierten. El
     * índice único evita duplicados si dos altas coinciden.
     *
     * Al eliminar un cliente, sus casos pasan a "Casos eliminados" (con sus
     * pagos y documentos intactos); al restaurar uno de esos casos, el
     * cliente vuelve también (ver Caso::booted()).
     */
    protected static function booted(): void
    {
        static::creating(function (Cliente $cliente): void {
            // withTrashed(): los eliminados también tienen su N°, no se repite.
            $cliente->nro_cliente ??= ((int) static::withTrashed()->lockForUpdate()->max('nro_cliente')) + 1;
        });

        static::deleted(function (self $cliente): void {
            if (! $cliente->isForceDeleting()) {
                // Sus casos quedan con la misma fecha, el motivo y el
                // documento del cliente (ver Eliminacion).
                $cliente->casos()->get()->each(function (Caso $caso) use ($cliente): void {
                    $caso->forceFill([
                        'fecha_eliminacion' => $cliente->fecha_eliminacion ?? now(),
                        'motivo_eliminacion' => 'Se eliminó el cliente'.($cliente->motivo_eliminacion ? ': '.$cliente->motivo_eliminacion : ''),
                        'documento_eliminacion' => $cliente->documento_eliminacion,
                    ])->saveQuietly();
                    $caso->delete();
                });
            }
        });
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->ap_paterno} {$this->ap_materno}");
    }

    /** Caso más reciente: de él salen abogado y fechas del proceso en la tabla de Clientes. */
    public function ultimoCaso(): HasOne
    {
        return $this->hasOne(Caso::class)->latestOfMany();
    }

    public function casos(): HasMany
    {
        return $this->hasMany(Caso::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoCliente::class);
    }

    public function informesCierre(): HasMany
    {
        return $this->hasMany(InformeCierreCaso::class);
    }
}
