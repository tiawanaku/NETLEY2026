<?php

namespace App\Models;

use App\Enums\Especialidad;
use App\Enums\EstadoCaso;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caso extends Model
{
    use HasFactory;
    // Eliminar un caso lo manda a "Casos eliminados" (se puede restaurar).
    use SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'especialidad',
        'materia_texto',
        'delito_id',
        'delito_texto',
        'descripcion',
        'apersonamiento',
        'servicio_psicologia',
        'servicio_trabajo_social',
        'servicio_otro',
        'servicio_otro_detalle',
        'monto_servicio_psicologia',
        'monto_servicio_trabajo_social',
        'monto_servicio_otro',
        'denunciado',
        'iguala',
        'saldo',
        'pagado',
        'modalidad_pago',
        'patrocinio_hih',
        'porcentaje_patrocinio',
        'monto_patrocinio',
        'comision_porcentaje',
        'comision_monto',
        'fecha_inicio',
        'duracion_meses',
        'fecha_fin',
        'estado',
        'ciudad',
        'documentos_pendientes',
        'fecha_eliminacion',
        'motivo_eliminacion',
        'documento_eliminacion',
    ];

    /**
     * Al eliminar (borrado lógico, ver "Casos eliminados") el caso queda con
     * estado Eliminado; al restaurarlo, vuelve a Activo.
     */
    protected static function booted(): void
    {
        static::deleted(function (self $caso): void {
            if (! $caso->isForceDeleting()) {
                $caso->forceFill(['estado' => EstadoCaso::Eliminado])->saveQuietly();
            }
        });

        static::restored(function (self $caso): void {
            $caso->forceFill(['estado' => EstadoCaso::Activo, 'fecha_eliminacion' => null, 'motivo_eliminacion' => null, 'documento_eliminacion' => null])->saveQuietly();

            // Si el cliente se había eliminado junto con sus casos, vuelve.
            $cliente = Cliente::withTrashed()->find($caso->cliente_id);

            if ($cliente?->trashed()) {
                $cliente->restore();
                $cliente->forceFill(['fecha_eliminacion' => null, 'motivo_eliminacion' => null, 'documento_eliminacion' => null])->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'especialidad' => Especialidad::class,
            'estado' => EstadoCaso::class,
            'servicio_psicologia' => 'boolean',
            'servicio_trabajo_social' => 'boolean',
            'servicio_otro' => 'boolean',
            'monto_servicio_psicologia' => 'decimal:2',
            'monto_servicio_trabajo_social' => 'decimal:2',
            'monto_servicio_otro' => 'decimal:2',
            'iguala' => 'decimal:2',
            'saldo' => 'decimal:2',
            'pagado' => 'decimal:2',
            'patrocinio_hih' => 'boolean',
            'documentos_pendientes' => 'boolean',
            'fecha_eliminacion' => 'date',
            'porcentaje_patrocinio' => 'decimal:2',
            'monto_patrocinio' => 'decimal:2',
            'comision_porcentaje' => 'decimal:2',
            'comision_monto' => 'decimal:2',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    /**
     * Materia legal para mostrar: la del catálogo o, si se eligió "Otros",
     * la escrita a mano.
     */
    public function getMateriaLegalAttribute(): string
    {
        return $this->especialidad?->getLabel() ?? $this->materia_texto ?? '—';
    }

    public function cliente(): BelongsTo
    {
        // También el cliente eliminado: un caso en "Casos eliminados" sigue
        // mostrando de quién es.
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function delito(): BelongsTo
    {
        return $this->belongsTo(Delito::class);
    }

    /**
     * "Denuncias": delitos del catálogo denunciados en el caso (varios).
     * Adicional a delito(), que es el delito / materia específica principal.
     */
    public function delitosDenunciados(): BelongsToMany
    {
        return $this->belongsToMany(Delito::class, 'caso_delito_denunciado')->withTimestamps();
    }

    public function personal(): BelongsToMany
    {
        return $this->belongsToMany(Personal::class, 'caso_personal');
    }

    public function consultas(): HasMany
    {
        return $this->hasMany(Consulta::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function planesPago(): HasMany
    {
        return $this->hasMany(PlanPago::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoCliente::class);
    }

    public function informeCierre(): HasOne
    {
        return $this->hasOne(InformeCierreCaso::class)->latestOfMany();
    }

    public function informesCierre(): HasMany
    {
        return $this->hasMany(InformeCierreCaso::class);
    }

    public function fiscalias(): HasMany
    {
        return $this->hasMany(Fiscalia::class);
    }

    public function juzgados(): HasMany
    {
        return $this->hasMany(Juzgado::class);
    }

    public function otrosSeguimientos(): HasMany
    {
        return $this->hasMany(OtroSeguimiento::class);
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoCaso::class);
    }

    public function seguimientosAgendados(): HasMany
    {
        return $this->hasMany(SeguimientoAgendado::class);
    }
}
