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
        'tipo_registro',
        'fecha_seguimiento',
        'responsable',
        'etapa_proceso',
        'instancia',
        'tipo_actuacion',
        'observaciones',
        'proxima_fecha',
        'proxima_actuacion',
        'proxima_cumplida_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_seguimiento' => 'datetime',
            'proxima_fecha' => 'date',
            'proxima_cumplida_at' => 'datetime',
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

    /** Actuados a realizar planificados en este registro. */
    public function actuados(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ActuadoCaso::class, 'seguimiento_id');
    }

    /** Actuados marcados como concluidos en este informe. */
    public function actuadosConcluidos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ActuadoCaso::class, 'informe_id');
    }

    public function esInforme(): bool
    {
        return $this->tipo_registro === 'informe';
    }

    /** Nombre del abogado: el del personal vinculado o el texto antiguo. */
    public function getAbogadoNombreAttribute(): ?string
    {
        return $this->personal?->nombre_completo ?: $this->responsable;
    }
}
