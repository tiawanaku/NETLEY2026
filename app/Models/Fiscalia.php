<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Fiscalia extends Model
{
    use HasFactory;

    protected $fillable = [
        'caso_id',
        'fecha_inicio',
        'fecha_fin',
        'vigente',
        'num_caso',
        'fiscalia_num',
        'nombre_fiscal',
        'telefono_fiscal',
        'investigador',
        'telefono_investigador',
        'auxiliar',
        'telefono_auxiliar',
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
