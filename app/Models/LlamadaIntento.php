<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlamadaIntento extends Model
{
    use HasFactory;

    protected $table = 'llamada_intentos';

    protected $fillable = [
        'llamada_id',
        'started_at',
        'ended_at',
        'duracion_segundos',
        'resultado',
        'notas',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function llamada(): BelongsTo
    {
        return $this->belongsTo(Llamada::class);
    }
}
