<?php

namespace App\Models;

use App\Enums\Especialidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Llamada extends Model
{
    use HasFactory;

    protected $fillable = [
        'personal_id',
        'personal_texto',
        'consulta_id',
        'nombres',
        'ap_paterno',
        'ap_materno',
        'motivo',
        'observaciones',
        'observaciones_netley',
        'creado_por',
        'numero_consulta',
        'ciudad',
        'materia_legal',
        'delito_id',
        'delito_texto',
        'iguala',
        'anticipo',
        'comision_bs',
        'comision_pct',
        'accion',
        'fecha',
        'hora',
        'telefono',
        'whatsapp',
        'duracion',
        'intentos',
        'origen',
    ];

    protected function casts(): array
    {
        return [
            'materia_legal' => Especialidad::class,
            'fecha' => 'date',
            'iguala' => 'decimal:2',
            'anticipo' => 'decimal:2',
            'comision_bs' => 'decimal:2',
            'comision_pct' => 'decimal:2',
        ];
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->ap_paterno} {$this->ap_materno}");
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }

    public function delito(): BelongsTo
    {
        return $this->belongsTo(Delito::class);
    }

    public function intentosLlamada(): HasMany
    {
        return $this->hasMany(LlamadaIntento::class);
    }
}
