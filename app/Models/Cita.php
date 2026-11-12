<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cita extends Model
{
    use HasFactory;

    protected $fillable = [
        'consulta_id',
        'caso_id',
        'fecha',
        'hora',
        'detalle',
        'observaciones',
        'observacion_cierre',
        'tipo',
        'origen',
        'anulada',
        'bs_iguala',
        'bs_anticipo',
        'porcentaje_comision',
        'bs_comision',
        'confirmada',
        'documentos',
        'servicio_psicologia',
        'servicio_trabajo_social',
        'servicio_otro',
        'servicio_otro_detalle',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'anulada' => 'boolean',
            'confirmada' => 'boolean',
            'bs_iguala' => 'decimal:2',
            'bs_anticipo' => 'decimal:2',
            'porcentaje_comision' => 'decimal:2',
            'bs_comision' => 'decimal:2',
            'documentos' => 'array',
            'servicio_psicologia' => 'boolean',
            'servicio_trabajo_social' => 'boolean',
            'servicio_otro' => 'boolean',
        ];
    }

    /** Servicios requeridos marcados, para mostrar ("Psicología, Trabajo social, Otro: ..."). */
    public function getServiciosRequeridosAttribute(): string
    {
        return collect([
            $this->servicio_psicologia ? 'Psicología' : null,
            $this->servicio_trabajo_social ? 'Trabajo social' : null,
            $this->servicio_otro ? 'Otro'.($this->servicio_otro_detalle ? ': '.$this->servicio_otro_detalle : '') : null,
        ])->filter()->join(', ');
    }

    public function observacionesCon(string $accion, string $texto): string
    {
        $entrada = now()->format('d/m/Y H:i')." — {$accion}: ".trim($texto);

        return $this->observaciones ? "{$this->observaciones}\n{$entrada}" : $entrada;
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    public function personal(): BelongsToMany
    {
        return $this->belongsToMany(Personal::class, 'cita_personal');
    }

    public function informe(): HasOne
    {
        return $this->hasOne(InformeCita::class);
    }
}
