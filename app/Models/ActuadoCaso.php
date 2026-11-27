<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un actuado del seguimiento del proceso (ver migración actuados_caso):
 * se planifica al "Registrar seguimiento" y se concluye en el "Informe".
 */
class ActuadoCaso extends Model
{
    protected $table = 'actuados_caso';

    protected $fillable = [
        'caso_id',
        'seguimiento_id',
        'informe_id',
        'tipo',
        'detalle',
        'fecha',
        'hora',
        'alarma',
        'concluido',
        'concluido_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'alarma' => 'boolean',
            'concluido' => 'boolean',
            'concluido_at' => 'datetime',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(SeguimientoCaso::class, 'seguimiento_id');
    }

    public function informe(): BelongsTo
    {
        return $this->belongsTo(SeguimientoCaso::class, 'informe_id');
    }

    /** No concluidos: siguen pendientes (pasan de un día al otro). */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('concluido', false);
    }

    /** Vencido: con fecha anterior a hoy y sin concluir. */
    public function getVencidoAttribute(): bool
    {
        return ! $this->concluido && $this->fecha !== null && $this->fecha->lt(now()->startOfDay());
    }

    /** "28/11/2026 14:00" (o solo la fecha / nada). */
    public function getCuandoAttribute(): ?string
    {
        if (! $this->fecha) {
            return $this->hora ? substr($this->hora, 0, 5) : null;
        }

        return $this->fecha->format('d/m/Y').($this->hora ? ' '.substr($this->hora, 0, 5) : '');
    }
}
