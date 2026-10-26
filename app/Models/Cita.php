<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cita extends Model
{
    use HasFactory;

    protected $fillable = [
        'consulta_id',
        'caso_id',
        'fecha',
        'hora',
        'detalle',
        'observaciones',
        'tipo',
        'origen',
        'anulada',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'anulada' => 'boolean',
        ];
    }

    public function observacionesCon(string $accion, string $texto): string
    {
        $entrada = now()->format('d/m/Y H:i')." — {$accion}: ".trim($texto);

        return $this->observaciones ? "{$this->observaciones}\n{$entrada}" : $entrada;
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    public function personal(): BelongsToMany
    {
        return $this->belongsToMany(Personal::class, 'cita_personal');
    }

    public function informe(): HasOne
    {
        return $this->hasOne(InformeCita::class);
    }
}
