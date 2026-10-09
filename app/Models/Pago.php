<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    use HasFactory;

    protected $fillable = [
        'caso_id',
        'cliente_id',
        'monto',
        'tipo_pago',
        'forma_pago',
        'nro_cheque',
        'banco',
        'fecha_pago',
        'nro_cuota',
        'sucursal',
        'nro_recibo',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'date',
        ];
    }

    public function montoEnLetras(): string
    {
        $entero = (int) floor((float) $this->monto);
        $centavos = (int) round(((float) $this->monto - $entero) * 100);

        if (! extension_loaded('intl')) {
            return number_format((float) $this->monto, 2, '.', ',').' Bolivianos';
        }

        $letras = (new \NumberFormatter('es', \NumberFormatter::SPELLOUT))->format($entero);

        return mb_strtoupper(mb_substr($letras, 0, 1)).mb_substr($letras, 1)
            .' '.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100 Bolivianos';
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
