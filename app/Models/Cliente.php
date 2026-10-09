<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        ];
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->ap_paterno} {$this->ap_materno}");
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
