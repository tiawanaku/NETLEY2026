<?php

namespace App\Models;

use App\Enums\Especialidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Respuesta extends Model
{
    use HasFactory;

    protected $fillable = [
        'consulta_id',
        'personal_id',
        'respuesta',
        'paso',
        'designacion',
        'materia_texto',
        'delito_id',
        'delito_texto',
        'publicado',
        'fecha_respuesta',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'designacion' => Especialidad::class,
            'publicado' => 'boolean',
            'fecha_respuesta' => 'datetime',
        ];
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function delito(): BelongsTo
    {
        return $this->belongsTo(Delito::class);
    }
}
