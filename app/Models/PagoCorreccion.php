<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una corrección hecha a un pago ya registrado ("Editar pago"), con su justificación. */
class PagoCorreccion extends Model
{
    protected $table = 'pago_correcciones';

    protected $fillable = [
        'pago_id',
        'caso_id',
        'justificacion',
        'antes',
        'despues',
        'corregido_por',
    ];

    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'despues' => 'array',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }
}
