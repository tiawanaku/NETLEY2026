<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contacto extends Model
{
    use HasFactory;

    protected $fillable = [
        'institucion',
        'entidad',
        'unidad',
        'cargo',
        'profesion',
        'nombre',
        'apellido',
        'direccion',
        'zona',
        'ciudad',
        'correo',
        'telefono',
        'horario_contacto',
        'nota',
    ];

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido}");
    }

    public function personal(): BelongsToMany
    {
        return $this->belongsToMany(Personal::class, 'contacto_personal');
    }
}
