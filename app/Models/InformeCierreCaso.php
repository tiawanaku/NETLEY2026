<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformeCierreCaso extends Model
{
    use HasFactory;

    protected $table = 'informes_cierre_caso';

    protected $fillable = [
        'caso_id',
        'cliente_id',
        'resultado',
        'saldo',
        'perdida',
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
