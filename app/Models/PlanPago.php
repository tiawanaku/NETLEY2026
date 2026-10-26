<?php

namespace App\Models;

use App\Enums\EstadoCuota;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPago extends Model
{
    use HasFactory;

    protected $table = 'planes_pago';

    protected $fillable = [
        'caso_id',
        'numero',
        'fecha',
        'monto',
        'nuevo_saldo',
        'estado',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoCuota::class,
            'fecha' => 'date',
            'monto' => 'decimal:2',
            'nuevo_saldo' => 'decimal:2',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }
}
