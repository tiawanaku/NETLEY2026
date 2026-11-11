<?php

namespace App\Models;

use App\Enums\Especialidad;
use App\Enums\EstadoConsulta;
use App\Enums\FormaIngreso;
use App\Support\Origenes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Consulta extends Model
{
    use HasFactory;

    protected $fillable = [
        'caso_id',
        'forma_ingreso',
        'fecha_registro',
        'atendido_por_id',
        'nombres',
        'ap_paterno',
        'ap_materno',
        'telefono',
        'whatsapp',
        'correo',
        'consulta',
        'designacion',
        'materia_texto',
        'delito_id',
        'delito_texto',
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
     * El detalle del origen solo se guarda con origen "Talleres" o "Referido" (ver
     * App\Support\Origenes); con otro origen se descarta.
     */
    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->origen_detalle = Origenes::limpiarDetalle($model->origen, $model->origen_detalle);

            // Sea cual sea la pantalla que la cierre, queda la fecha en que
            // pasó a un estado cerrado; al volver a abrirse (reactivar), se
            // borra.
            if ($model->isDirty('estado') && $model->estado) {
                $abierta = in_array($model->estado, EstadoConsulta::abiertos(), true);

                if ($abierta) {
                    $model->fecha_cierre = null;
                } elseif (! $model->isDirty('fecha_cierre')) {
                    $model->fecha_cierre = now();
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'forma_ingreso' => FormaIngreso::class,
            'estado' => EstadoConsulta::class,
            'designacion' => Especialidad::class,
            'fecha_consulta' => 'datetime',
            'fecha_contacto' => 'date',
            'fecha_cierre' => 'date',
            'fecha_registro' => 'date',
            'ubicacion' => 'array',
        ];
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->ap_paterno} {$this->ap_materno}");
    }

    /**
     * Materia legal para mostrar: la del catálogo o, si se eligió "Otro", la
     * escrita a mano.
     */
    public function getMateriaLegalAttribute(): string
    {
        return $this->designacion?->getLabel() ?? $this->materia_texto ?? '—';
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'atendido_por_id');
    }

    public function delito(): BelongsTo
    {
        return $this->belongsTo(Delito::class);
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(Respuesta::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** La cita no anulada más reciente: la que se muestra en "Agendados". */
    public function citaVigente(): HasOne
    {
        return $this->hasOne(Cita::class)->where('anulada', false)->latestOfMany();
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
