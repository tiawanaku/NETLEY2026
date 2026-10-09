<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeguimientoAgendado extends Model
{
    use HasFactory;

    protected $table = 'seguimientos_agendados';

    protected $fillable = [
        'caso_id',
        'personal_id',
        'instancia_type',
        'instancia_id',
        'fecha',
        'hora',
        'detalles',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function instancia(): MorphTo
    {
        return $this->morphTo();
    }
}
