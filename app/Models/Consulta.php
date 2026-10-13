<?php

namespace App\Models;

use App\Enums\EstadoConsulta;
use App\Support\Origenes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consulta extends Model
{
    use HasFactory;

    protected $fillable = [
        'caso_id',
        'nombres',
        'ap_paterno',
        'ap_materno',
        'telefono',
        'whatsapp',
        'correo',
        'consulta',
        'nota_interna',
        'denunciado',
        'fecha_consulta',
        'pais',
        'ciudad',
        'provincia',
        'direccion',
        'zona',
        'calles',
        'numero_domicilio',
        'indicaciones_domicilio',
        'ubicacion',
        'estado',
        'fecha_contacto',
        'origen',
        'origen_detalle',
    ];

    /**
     * El detalle del origen solo se guarda con origen "Talleres" (ver
     * App\Support\Origenes); con otro origen se descarta.
     */
    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->origen_detalle = Origenes::limpiarDetalle($model->origen, $model->origen_detalle);
        });
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoConsulta::class,
            'fecha_consulta' => 'datetime',
            'fecha_contacto' => 'date',
            'ubicacion' => 'array',
        ];
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->ap_paterno} {$this->ap_materno}");
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(Respuesta::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /**
     * "Denuncias": delitos del catálogo denunciados (varios). Al ascender la
     * consulta a cliente ejecutivo pasan al caso.
     */
    public function delitosDenunciados(): BelongsToMany
    {
        return $this->belongsToMany(Delito::class, 'consulta_delito_denunciado')->withTimestamps();
    }

    public function llamadas(): HasMany
    {
        return $this->hasMany(Llamada::class);
    }
}
