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
        'personal_id',
        'fecha_seguimiento',
        'responsable',
        'etapa_proceso',
        'instancia',
        'tipo_actuacion',
        'observaciones',
        'proxima_fecha',
        'proxima_actuacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_seguimiento' => 'datetime',
            'proxima_fecha' => 'date',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    /** Nombre del abogado: el del personal vinculado o el texto antiguo. */
    public function getAbogadoNombreAttribute(): ?string
    {
        return $this->personal?->nombre_completo ?: $this->responsable;
    }
}
