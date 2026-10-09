<?php

namespace App\Models;

use App\Enums\Especialidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Taller extends Model
{
    use HasFactory;

    protected $table = 'talleres';

    protected $fillable = [
        'fecha_programada',
        'hora_programada',
        'nombres',
        'ap_paterno',
        'ap_materno',
        'telefono',
        'whatsapp',
        'ciudad',
        'motivo',
        'numero_consulta',
        'materia_legal',
        'delito_id',
        'delito_texto',
        'consulta',
        'respuesta',
        'creado_por',
        'personal_texto',
        'abogado_texto',
        'origen',
    ];

    protected function casts(): array
    {
        return [
            'materia_legal' => Especialidad::class,
            'fecha_programada' => 'date',
        ];
    }

    public function delito(): BelongsTo
    {
        return $this->belongsTo(Delito::class);
    }
}
