<?php

namespace App\Models;

use App\Enums\RedSocialPlataforma;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RedSocial extends Model
{
    use HasFactory;

    protected $table = 'redes_sociales';

    protected $fillable = [
        'plataforma',
        'url',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'plataforma' => RedSocialPlataforma::class,
            'activo' => 'boolean',
        ];
    }
}
