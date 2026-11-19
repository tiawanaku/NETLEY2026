<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformeCierreCaso extends Model
{
    use HasFactory;

    protected $table = 'informes_cierre_caso';

    /** Opciones de "Resultado" del cierre (se guarda la clave). */
    public const RESULTADOS = [
        'se_gano_proceso' => 'Se ganó el proceso',
        'se_perdio_proceso' => 'Se perdió el proceso',
        'transaccion' => 'Transacción / acuerdo',
        'desistimiento' => 'Desistimiento',
        'abandono' => 'Abandono',
        'otro' => 'Otro',
    ];

    /** Resultado para mostrar; los importados con texto libre se ven tal cual. */
    public function getResultadoTextoAttribute(): ?string
    {
        return self::RESULTADOS[$this->resultado] ?? $this->resultado;
    }

    protected $fillable = [
        'caso_id',
        'cliente_id',
        'resultado',
        'abogado_responsable',
        'saldo',
        'perdida',
        'perdida_hih',
        'responsable_hih',
        'perdida_netley',
        'responsable_netley',
        'asume',
        'seguimiento_responsable',
        'opciones',
        'nota_netley',
        'creado_por',
        'fecha_cierre',
    ];

    protected function casts(): array
    {
        return [
            'saldo' => 'decimal:2',
            'perdida' => 'decimal:2',
            'perdida_hih' => 'decimal:2',
            'perdida_netley' => 'decimal:2',
            'fecha_cierre' => 'date',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
