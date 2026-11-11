<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoPersonal extends Model
{
    use HasFactory;

    protected $table = 'documentos_personal';

    protected $fillable = [
        'personal_id',
        'nombre',
        'ruta',
        'fecha',
    ];

    /** Los que se suben con el clip llegan sin fecha: queda la del día en que se subió. */
    protected static function booted(): void
    {
        static::creating(function (self $documento): void {
            $documento->fecha ??= today();
        });
    }

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }
}
