<?php

namespace App\Models;

use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoCliente extends Model
{
    use HasFactory;

    protected $table = 'documentos_cliente';

    protected $fillable = [
        'cliente_id',
        'caso_id',
        'ruta',
        'descripcion',
        'tipo',
        'tipo_detalle',
        'subido_por_cliente',
        'fecha_origen',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumento::class,
            'subido_por_cliente' => 'boolean',
            'fecha_origen' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }
}
