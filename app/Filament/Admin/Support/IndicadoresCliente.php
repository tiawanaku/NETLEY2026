<?php

namespace App\Filament\Admin\Support;

use App\Enums\EstadoCaso;
use App\Enums\EstadoCuota;
use App\Models\Caso;
use App\Models\Cliente;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Columna "Indicadores" de la tabla de Clientes: símbolos por fila,
 * calculados sobre los casos ABIERTOS del cliente (no Cerrado) — el peor
 * caso entre varios manda, para que la fila avise aunque sea uno solo de
 * sus procesos el atrasado.
 *
 * - $ (pagos): verde al día, amarillo con 1-5 días de atraso en alguna
 *   cuota, rojo con más de 5.
 * - Balanza (vencimiento del proceso): verde normal, amarillo a ~15 días o
 *   menos de la fecha de fin, rojo a 1 día o menos (incluye ya vencido).
 * - Documento (alerta, solo si corresponde): algún caso quedó con
 *   "documentos_pendientes" (ver ClienteCasoWizard) y todavía no se le
 *   subió ninguno.
 *
 * Requiere `casos.planesPago` precargado (ver ClientesTable::modifyQueryUsing)
 * para no disparar una consulta por fila.
 */
class IndicadoresCliente
{
    public static function render(Cliente $cliente): HtmlString
    {
        $casosAbiertos = $cliente->casos->where('estado', '!=', EstadoCaso::Cerrado);

        [$colorPago, $tituloPago] = self::estadoPago($casosAbiertos);
        [$colorVencimiento, $tituloVencimiento] = self::estadoVencimiento($casosAbiertos);

        $html = self::icono('heroicon-o-currency-dollar', $colorPago, $tituloPago)
            .self::icono('heroicon-o-scale', $colorVencimiento, $tituloVencimiento);

        if ($casosAbiertos->contains('documentos_pendientes', true)) {
            $html .= self::icono('heroicon-o-document-arrow-up', self::AMARILLO, 'Falta subir el documento de un caso');
        }

        return new HtmlString($html);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Caso>  $casos
     * @return array{0: string, 1: string}
     */
    protected static function estadoPago($casos): array
    {
        $hoy = now()->startOfDay();

        $diasAtraso = $casos
            ->flatMap(fn (Caso $caso) => $caso->planesPago)
            ->filter(fn ($cuota) => $cuota->estado === EstadoCuota::Pendiente && $cuota->fecha->startOfDay()->lt($hoy))
            ->max(fn ($cuota) => $cuota->fecha->startOfDay()->diffInDays($hoy));

        if (! $diasAtraso) {
            return [self::VERDE, 'Pagos al día'];
        }

        if ($diasAtraso <= 5) {
            return [self::AMARILLO, "Pago atrasado {$diasAtraso} día(s)"];
        }

        return [self::ROJO, "Pago atrasado {$diasAtraso} día(s)"];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Caso>  $casos
     * @return array{0: string, 1: string}
     */
    protected static function estadoVencimiento($casos): array
    {
        /** @var Carbon|null $proximaFecha */
        $proximaFecha = $casos
            ->pluck('fecha_fin')
            ->filter()
            ->sort()
            ->first();

        if (! $proximaFecha) {
            return [self::VERDE, 'Sin fecha de vencimiento próxima'];
        }

        $diasRestantes = now()->startOfDay()->diffInDays($proximaFecha->copy()->startOfDay(), false);

        if ($diasRestantes <= 1) {
            return $diasRestantes < 0
                ? [self::ROJO, 'Proceso vencido hace '.abs($diasRestantes).' día(s)']
                : [self::ROJO, $diasRestantes === 0 ? 'Vence hoy' : 'Vence mañana'];
        }

        if ($diasRestantes <= 15) {
            return [self::AMARILLO, "Vence en {$diasRestantes} día(s)"];
        }

        return [self::VERDE, "Vence en {$diasRestantes} día(s)"];
    }

    protected const VERDE = '#16a34a';

    protected const AMARILLO = '#ca8a04';

    protected const ROJO = '#dc2626';

    protected static function icono(string $heroicon, string $color, string $titulo): string
    {
        $svg = svg($heroicon, '', ['style' => "width:1.25rem;height:1.25rem;color:{$color};"])->toHtml();

        return '<span title="'.e($titulo).'" style="display:inline-flex;margin-right:.5rem;vertical-align:middle;">'.$svg.'</span>';
    }
}
