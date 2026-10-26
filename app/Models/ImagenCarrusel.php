<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagenCarrusel extends Model
{
    use HasFactory;

    protected $table = 'imagenes_carrusel';

    protected $fillable = [
        'imagen',
        'titulo',
        'subtitulo',
        'enlace',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
