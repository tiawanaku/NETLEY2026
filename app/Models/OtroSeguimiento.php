<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OtroSeguimiento extends Model
{
    use HasFactory;

    protected $table = 'otros_seguimientos';

    protected $fillable = [
        'caso_id',
        'nombre_instancia',
        'fecha_inicio',
        'fecha_fin',
        'num_caso',
        'nombre_contacto',
        'numero_contacto',
        'detalles',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
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
