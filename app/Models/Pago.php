<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'anulado',
        'anulado_at',
        'motivo_anulacion',
        'anulado_por',
        'reemplazado_por_id',
    ];

    /**
     * Los recibos anulados (ver PlanPagoRecalculo::corregirPago()) quedan
     * fuera de todas las consultas — totales, plan de pagos, historial,
     * cronología… — salvo en Contabilidad › Recibos anulados, que los busca
     * con withoutGlobalScope('vigentes').
     */
    protected static function booted(): void
    {
        static::addGlobalScope('vigentes', fn (Builder $query) => $query->where('pagos.anulado', false));
    }

    /** Próximo N° de recibo: correlativo contando también los anulados (nunca se repite). */
    public static function siguienteRecibo(): int
    {
        return ((int) static::withoutGlobalScope('vigentes')->max('nro_recibo')) + 1;
    }

    /** Recibo nuevo que reemplazó a este (si está anulado). */
    public function reemplazadoPor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reemplazado_por_id');
    }

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'date',
            'anulado' => 'boolean',
            'anulado_at' => 'datetime',
        ];
    }

    /**
     * Personal detrás de registrado_por (que guarda el username de la
     * cuenta, no el nombre) — para mostrar nombre y carnet reales en el
     * recibo en vez de las credenciales de acceso.
     */
    public function registradoPorPersonal(): ?Personal
    {
        if (blank($this->registrado_por)) {
            return null;
        }

        return User::where('username', $this->registrado_por)->first()?->personal;
    }

    /** "Anticipo", "Cuota N° 3" o "A cuenta": qué se pagó (tablas y recibo). */
    public function getConceptoAttribute(): string
    {
        if ($this->tipo_pago === 'anticipo') {
            return 'Anticipo';
        }

        return $this->nro_cuota > 0 ? 'Cuota N° '.$this->nro_cuota : 'A cuenta';
    }

    public function getRegistradoPorNombreAttribute(): ?string
    {
        return $this->registradoPorPersonal()?->nombre_completo ?? $this->registrado_por;
    }

    public function getRegistradoPorCiAttribute(): ?string
    {
        return $this->registradoPorPersonal()?->ci;
    }

    public function montoEnLetras(): string
    {
        $entero = (int) round((float) $this->monto);
        $centavos = 0; // Montos sin centavos en todo el sistema.

        if (! extension_loaded('intl')) {
            return number_format((float) $this->monto, 0, '.', ',').' Bolivianos';
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

    /**
     * Montos del recuadro derecho del recibo (pdf/recibo-pago):
     * - primero: ¿es el primer recibo del proceso? (lleva el resumen completo)
     * - saldo_anterior: lo que debía el cliente antes de este pago
     * - saldo_total: lo que queda después de este pago
     *
     * Se calcula con los pagos vigentes del caso hechos antes que este (por
     * fecha, cuota e id), sobre la iguala Netley; los anulados no cuentan
     * (un recibo anulado se compara con los vigentes que tenía delante).
     *
     * @return array{primero: bool, saldo_anterior: float, saldo_total: float}
     */
    public function datosRecibo(): array
    {
        $caso = $this->caso;
        $orden = fn (self $pago): array => [$pago->fecha_pago?->format('Y-m-d') ?? '', (int) $pago->nro_cuota, $pago->id];

        $anteriores = $caso
            ? $caso->pagos()->whereKeyNot($this->getKey())->get()
                ->filter(fn (self $otro) => $orden($otro) < $orden($this))
            : collect();

        $saldoAnterior = (float) ($caso?->iguala ?? 0) - (float) $anteriores->sum('monto');

        return [
            'primero' => $anteriores->isEmpty(),
            'saldo_anterior' => $saldoAnterior,
            'saldo_total' => $saldoAnterior - (float) $this->monto,
        ];
    }
}
