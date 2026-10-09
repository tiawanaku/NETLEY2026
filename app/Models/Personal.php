<?php

namespace App\Models;

use App\Casts\CsvArray;
use App\Enums\EstadoPersonal;
use App\Enums\Rol;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Personal extends Model
{
    use HasFactory;

    protected $table = 'personal';

    protected $fillable = [
        'rol',
        'nombres',
        'ap_paterno',
        'ap_materno',
        'genero',
        'ci',
        'ci_expedido',
        'fecha_nacimiento',
        'nacionalidad',
        'direccion',
        'telefono',
        'whatsapp',
        'correo',
        'estado',
        'cargo',
        'profesion',
        'especialidades',
        'tiene_contrato',
        'foto',
        'ciudad_residencia',
        'estado_civil',
        'fecha_inicio',
        'fecha_fin',
        'motivo_baja',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'rol' => Rol::class,
            'estado' => EstadoPersonal::class,
            'especialidades' => 'array',
            'cargo' => CsvArray::class,
            'tiene_contrato' => 'boolean',
            'fecha_nacimiento' => 'date',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'date',
        ];
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->ap_paterno} {$this->ap_materno}");
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function casos(): BelongsToMany
    {
        return $this->belongsToMany(Caso::class, 'caso_personal');
    }

    public function citas(): BelongsToMany
    {
        return $this->belongsToMany(Cita::class, 'cita_personal');
    }

    public function contactos(): BelongsToMany
    {
        return $this->belongsToMany(Contacto::class, 'contacto_personal');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(Respuesta::class);
    }

    public function llamadas(): HasMany
    {
        return $this->hasMany(Llamada::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoPersonal::class);
    }

    public function seguimientosAgendados(): HasMany
    {
        return $this->hasMany(SeguimientoAgendado::class);
    }

    /** Para una procuradora: los abogados que tiene asignados. */
    public function abogadosAsignados(): BelongsToMany
    {
        return $this->belongsToMany(Personal::class, 'abogado_procurador', 'procurador_id', 'abogado_id');
    }

    /** Para un abogado: las procuradoras que lo asisten. */
    public function procuradorasAsignadas(): BelongsToMany
    {
        return $this->belongsToMany(Personal::class, 'abogado_procurador', 'abogado_id', 'procurador_id');
    }
}
