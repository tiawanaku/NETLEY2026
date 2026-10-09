<?php

namespace App\Models;

use App\Enums\EstadoAprobacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonio extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'testimonio',
        'calificacion',
        'correo',
        'fecha',
        'estado',
        'visible',
        'notas_admin',
        'fecha_modificacion',
        'modificado_por',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoAprobacion::class,
            'fecha' => 'datetime',
            'visible' => 'boolean',
            'fecha_modificacion' => 'datetime',
        ];
    }
}
