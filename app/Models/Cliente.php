<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Además de ser el registro de un cliente del despacho, este modelo es
 * Authenticatable para el guard `cliente`: así el cliente entra al portal
 * (resources/views/portal/*) con su propio usuario/contraseña, sin depender
 * de la tabla `users` (esa es solo para el personal/panel Filament).
 */
class Cliente extends Model implements AuthenticatableContract
{
    use Authenticatable, HasFactory;

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
        ];
    }

    /**
     * N° de cliente correlativo (1, 2, 3…), asignado al darlo de alta por
     * cualquier vía (wizard, importación legacy, etc.). A diferencia del id
     * interno, no salta números por guardados que fallan y se revierten. El
     * índice único evita duplicados si dos altas coinciden.
     */
    protected static function booted(): void
    {
        static::creating(function (Cliente $cliente): void {
            $cliente->nro_cliente ??= ((int) static::query()->lockForUpdate()->max('nro_cliente')) + 1;
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
