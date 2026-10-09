<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeguimientoCaso extends Model
{
    use HasFactory;

    protected $table = 'seguimientos_caso';

    protected $fillable = [
        'caso_id',
        'fecha_seguimiento',
        'responsable',
        'etapa_proceso',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_seguimiento' => 'datetime',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }
}
