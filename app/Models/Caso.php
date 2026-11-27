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
        'caso_origen_id',
        'costas_cierre_fecha',
        'costas_cierre_justificacion',
        'especialidad',
        'materia_texto',
        'delito_id',
        'delito_texto',
        'descripcion',
        'caso_tipo',
        'caso_tipo_otro',
        'caso_detalle',
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
        // "Especificar" de Detalles del proceso solo vale con "Otro".
        static::saving(function (self $caso): void {
            if ($caso->caso_tipo !== 'otro') {
                $caso->caso_tipo_otro = null;
            }
        });

        // Proceso de costas y costos: su caso de origen queda "Pendiente"
        // mientras esté abierto y vuelve a "Cerrado" al cerrarlo (o eliminarlo).
        static::saved(fn (self $caso) => $caso->sincronizarOrigenCostas());

        static::deleted(function (self $caso): void {
            if (! $caso->isForceDeleting()) {
                $caso->forceFill(['estado' => EstadoCaso::Eliminado])->saveQuietly();
            }

            $caso->sincronizarOrigenCostas();
        });

        static::restored(function (self $caso): void {
            $caso->forceFill(['estado' => EstadoCaso::Activo, 'fecha_eliminacion' => null, 'motivo_eliminacion' => null, 'documento_eliminacion' => null])->saveQuietly();
            $caso->sincronizarOrigenCostas();

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
            'costas_cierre_fecha' => 'date',
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

    /**
     * "Caso" de Datos del proceso para listados: "NUREJ — 2026-123" (o el
     * tipo escrito a mano con "Otro"), con el detalle.
     */
    public function getCasoTextoAttribute(): ?string
    {
        $tipo = $this->caso_tipo === 'otro'
            ? ($this->caso_tipo_otro ?: \App\Filament\Admin\Resources\Casos\Schemas\CasoForm::TIPOS_CASO['otro'])
            : (\App\Filament\Admin\Resources\Casos\Schemas\CasoForm::TIPOS_CASO[$this->caso_tipo] ?? null);

        return collect([$tipo, $this->caso_detalle])->filter(fn ($valor) => filled($valor))->join(' — ') ?: null;
    }

    /**
     * Tipo(s) de proceso para listados: delito principal (o el escrito a
     * mano) y los delitos denunciados, sin repetir.
     *
     * @return array<int, string>
     */
    public function tiposProceso(): array
    {
        return collect([$this->delito?->delito ?? $this->delito_texto])
            ->merge($this->delitosDenunciados->pluck('delito'))
            ->filter(fn ($valor) => filled($valor))
            ->unique()
            ->values()
            ->all();
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

    /**
     * ¿Es un proceso de costas y costos? Ahí el patrocinio HiH también se
     * cobra: iguala/saldo = Iguala Netley + patrocinio (ver ClienteCasoWizard::plan()).
     */
    public function esCostas(): bool
    {
        return filled($this->caso_origen_id);
    }

    /** Iguala profesional: en costas ya es la iguala; si no, iguala Netley + patrocinio. */
    public function igualaProfesional(): float
    {
        return (float) $this->iguala + ($this->esCostas() || ! $this->patrocinio_hih ? 0.0 : (float) $this->monto_patrocinio);
    }

    /** Caso ganado del que salió este proceso de costas y costos. */
    public function casoOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'caso_origen_id');
    }

    /** Procesos de costas y costos iniciados a partir de este caso. */
    public function casosDerivados(): HasMany
    {
        return $this->hasMany(self::class, 'caso_origen_id');
    }

    /**
     * Si este es un proceso de costas y costos: al crearlo (o eliminarlo /
     * restaurarlo) vuelve a calcular el estado de su caso de origen.
     */
    public function sincronizarOrigenCostas(): void
    {
        if (blank($this->caso_origen_id)) {
            return;
        }

        self::withTrashed()->find($this->caso_origen_id)?->actualizarEstadoCostas();
    }

    /**
     * Caso ya cerrado: queda "Pendiente" mientras le falte iniciar costas y
     * costos (ganado, con patrocinio HiH y sin proceso de costas) y pasa a
     * "Cerrado" en cuanto se inicia. No toca un caso activo ni eliminado.
     */
    public function actualizarEstadoCostas(): void
    {
        if ($this->trashed() || ! $this->estado?->esCerrado()) {
            return;
        }

        // Cerrado sin costas ("Cerrar caso" del asistente de costas, con
        // fecha y justificación): Cerrado, aunque todavía pueda iniciarlas.
        $estado = $this->requiereCostasYCostos() && blank($this->costas_cierre_fecha)
            ? EstadoCaso::Pendiente
            : EstadoCaso::Cerrado;

        if ($this->estado !== $estado) {
            $this->forceFill(['estado' => $estado])->saveQuietly();
        }
    }

    /**
     * ¿Falta iniciar costas y costos? Caso cerrado (o pendiente), ganado
     * ("Se ganó el proceso" en el informe de cierre), con patrocinio HiH
     * mayor a 0 y sin un proceso de costas ya iniciado (contando los
     * eliminados).
     */
    public function requiereCostasYCostos(): bool
    {
        return (bool) $this->estado?->esCerrado()
            && $this->patrocinio_hih
            && (float) $this->monto_patrocinio > 0
            && $this->informeCierre?->resultado === 'se_gano_proceso'
            && ! $this->casosDerivados()->withTrashed()->exists();
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

    /** Actuados del seguimiento del proceso (ver ActuadoCaso). */
    public function actuados(): HasMany
    {
        return $this->hasMany(ActuadoCaso::class);
    }
}
