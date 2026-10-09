<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformeCita extends Model
{
    use HasFactory;

    protected $table = 'informes_cita';

    protected $fillable = [
        'cita_id',
        'fecha_informe',
        'detalle',
        'forma_ingreso',
        'nombre_colegio',
        'telefono',
        'nombres',
        'apellidos',
        'responsable',
        'acciones',
        'anticipo',
        'iguala',
        'comision_bs',
        'comision_pct',
        'redaccion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_informe' => 'date',
            'anticipo' => 'decimal:2',
            'iguala' => 'decimal:2',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }
}
