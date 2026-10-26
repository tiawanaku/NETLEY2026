<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Juzgado extends Model
{
    use HasFactory;

    protected $fillable = [
        'caso_id',
        'fecha_inicio',
        'fecha_fin',
        'vigente',
        'num_caso',
        'juzgado_num',
        'nombre_juez',
        'telefono_juez',
        'secretaria',
        'telefono_secretaria',
        'auxiliar',
        'telefono_auxiliar',
        'oficial',
        'telefono_oficial',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'vigente' => 'boolean',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    public function seguimientosAgendados(): MorphMany
    {
        return $this->morphMany(SeguimientoAgendado::class, 'instancia');
    }
}
