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

class Caso extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'especialidad',
        'materia_texto',
        'delito_id',
        'delito_texto',
        'descripcion',
        'apersonamiento',
        'iguala',
        'saldo',
        'pagado',
        'modalidad_pago',
        'patrocinio_hih',
        'porcentaje_patrocinio',
        'monto_patrocinio',
        'fecha_inicio',
        'duracion_meses',
        'fecha_fin',
        'estado',
        'ciudad',
    ];

    protected function casts(): array
    {
        return [
            'especialidad' => Especialidad::class,
            'estado' => EstadoCaso::class,
            'iguala' => 'decimal:2',
            'saldo' => 'decimal:2',
            'pagado' => 'decimal:2',
            'patrocinio_hih' => 'boolean',
            'porcentaje_patrocinio' => 'decimal:2',
            'monto_patrocinio' => 'decimal:2',
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
        return $this->belongsTo(Cliente::class);
    }

    public function delito(): BelongsTo
    {
        return $this->belongsTo(Delito::class);
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
