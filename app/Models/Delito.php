<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delito extends Model
{
    use HasFactory;

    protected $fillable = [
        'area',
        'delito',
    ];

    public function casos(): HasMany
    {
        return $this->hasMany(Caso::class);
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(Respuesta::class);
    }

    public function llamadas(): HasMany
    {
        return $this->hasMany(Llamada::class);
    }

    public function talleres(): HasMany
    {
        return $this->hasMany(Taller::class);
    }
}
