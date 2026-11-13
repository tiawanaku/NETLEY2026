<?php

namespace App\Support;

use App\Models\Caso;
use App\Models\Cita;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cronología del caso (pestaña "Cronología" de la ficha): junta en una sola
 * línea de tiempo todo lo que pasó con el caso — la consulta de origen y
 * sus citas, el inicio del caso, pagos, documentos, seguimientos,
 * fiscalías, juzgados, otras instancias, seguimientos agendados y cierres —
 * ordenado por fecha (lo más antiguo primero). Solo lee, no guarda nada.
 */
class CronologiaCaso
{
    /**
     * @return Collection<int, array{fecha: Carbon, hora: ?string, titulo: string, detalle: ?string, tipo: string, icono: string, color: string, futuro: bool}>
     */
    public static function eventos(Caso $caso): Collection
    {
        $caso->loadMissing([
            'consultas.citas.personal', 'citas.personal', 'pagos', 'documentos', 'seguimientos',
            'fiscalias', 'juzgados', 'otrosSeguimientos', 'seguimientosAgendados.personal', 'informesCierre', 'personal',
        ]);

        $eventos = collect();
        $bs = fn ($monto): string => 'Bs. '.(fmod((float) $monto, 1.0) == 0.0
            ? number_format((float) $monto, 0, '.', '')
            : number_format((float) $monto, 2, '.', ''));
        $agregar = function (?Carbon $fecha, string $tipo, string $titulo, ?string $detalle = null, ?string $hora = null) use ($eventos): void {
            if (! $fecha) {
                return;
            }

            [$icono, $color] = self::estilo($tipo);

            $eventos->push([
                'fecha' => $fecha->copy(),
                'hora' => $hora ? substr($hora, 0, 5) : null,
                'titulo' => $titulo,
                'detalle' => filled($detalle) ? $detalle : null,
                'tipo' => $tipo,
                'icono' => $icono,
                'color' => $color,
                'futuro' => $fecha->copy()->startOfDay()->isAfter(today()),
            ]);
        };

        // Consulta de origen y sus citas (antes de ser cliente ejecutivo).
        foreach ($caso->consultas as $consulta) {
            $agregar($consulta->fecha_registro ?? $consulta->created_at, 'consulta', 'Consulta registrada',
                collect([
                    $consulta->consulta ? '"'.str($consulta->consulta)->limit(160).'"' : null,
                    $consulta->atendidoPor ? 'Atendió: '.$consulta->atendidoPor->nombre_completo : null,
                ])->filter()->join(' · '));
        }

        $citas = $caso->consultas->flatMap->citas->merge($caso->citas)->unique('id');
        foreach ($citas as $cita) {
            /** @var Cita $cita */
            $abogados = $cita->personal->pluck('nombre_completo')->join(', ');
            $agregar($cita->fecha, 'cita', $cita->anulada ? 'Cita (anulada)' : 'Cita con el abogado',
                collect([$abogados ? 'Con: '.$abogados : null, $cita->detalle])->filter()->join(' · '), $cita->hora);
        }

        // Inicio del caso.
        $agregar($caso->fecha_inicio, 'inicio', 'Inicio del caso (cliente ejecutivo)',
            collect([
                $caso->materia_legal,
                $caso->delito?->delito ?? $caso->delito_texto,
                $caso->apersonamiento,
                $caso->personal->isNotEmpty() ? 'Abogado(s): '.$caso->personal->pluck('nombre_completo')->join(', ') : null,
                'Iguala Netley '.$bs($caso->iguala),
            ])->filter()->join(' · '));

        foreach ($caso->pagos as $pago) {
            $agregar($pago->fecha_pago, 'pago', 'Pago — '.$pago->concepto,
                collect([$bs($pago->monto), $pago->nro_recibo ? 'Recibo N° '.$pago->nro_recibo : null, $pago->forma_pago ? ucfirst($pago->forma_pago) : null])->filter()->join(' · '));
        }

        // Correcciones de pagos ("Editar pago"), con su justificación.
        foreach (\App\Models\PagoCorreccion::where('caso_id', $caso->id)->with('pago')->get() as $correccion) {
            $cambios = collect($correccion->despues)
                ->filter(fn ($valor, $campo) => ($correccion->antes[$campo] ?? null) != $valor)
                ->map(fn ($valor, $campo) => match ($campo) {
                    'monto' => 'monto '.$bs($correccion->antes['monto'] ?? 0).' → '.$bs($valor),
                    'fecha_pago' => 'fecha '.\Illuminate\Support\Carbon::parse($correccion->antes['fecha_pago'])->format('d/m/Y').' → '.\Illuminate\Support\Carbon::parse($valor)->format('d/m/Y'),
                    'forma_pago' => 'forma de pago '.ucfirst((string) ($correccion->antes['forma_pago'] ?? '—')).' → '.ucfirst((string) $valor),
                    default => $campo.' corregido',
                })
                ->join(', ');

            $agregar($correccion->created_at, 'correccion',
                'Pago corregido'.($correccion->pago?->nro_recibo ? ' (recibo N° '.$correccion->pago->nro_recibo.')' : ''),
                collect([$cambios ?: null, 'Justificación: '.$correccion->justificacion, $correccion->corregido_por ? 'Por: '.$correccion->corregido_por : null])->filter()->join(' · '),
                $correccion->created_at->format('H:i'));
        }

        foreach ($caso->documentos as $documento) {
            $agregar($documento->fecha_origen ?? $documento->created_at, 'documento',
                'Documento añadido'.($documento->subido_por_cliente ? ' por el cliente' : ''),
                $documento->descripcion ?: basename((string) $documento->ruta));
        }

        foreach ($caso->seguimientos as $seguimiento) {
            $agregar($seguimiento->fecha_seguimiento, 'seguimiento',
                'Seguimiento'.($seguimiento->etapa_proceso ? ' — '.$seguimiento->etapa_proceso : ''),
                collect([$seguimiento->observaciones, $seguimiento->responsable ? 'Responsable: '.$seguimiento->responsable : null])->filter()->join(' · '),
                $seguimiento->fecha_seguimiento?->format('H:i') !== '00:00' ? $seguimiento->fecha_seguimiento?->format('H:i') : null);
        }

        foreach ($caso->fiscalias as $fiscalia) {
            $nombre = 'Fiscalía'.($fiscalia->fiscalia_num ? ' N° '.$fiscalia->fiscalia_num : '');
            $detalle = collect([$fiscalia->num_caso ? 'Caso '.$fiscalia->num_caso : null, $fiscalia->nombre_fiscal ? 'Fiscal: '.$fiscalia->nombre_fiscal : null])->filter()->join(' · ');
            $agregar($fiscalia->fecha_inicio, 'instancia', 'Ingreso a '.$nombre, $detalle);
            $agregar($fiscalia->fecha_fin, 'instancia_fin', 'Salida de '.$nombre, $detalle);
        }

        foreach ($caso->juzgados as $juzgado) {
            $nombre = 'Juzgado'.($juzgado->juzgado_num ? ' N° '.$juzgado->juzgado_num : '');
            $detalle = collect([$juzgado->num_caso ? 'Caso '.$juzgado->num_caso : null, $juzgado->nombre_juez ? 'Juez: '.$juzgado->nombre_juez : null])->filter()->join(' · ');
            $agregar($juzgado->fecha_inicio, 'instancia', 'Ingreso a '.$nombre, $detalle);
            $agregar($juzgado->fecha_fin, 'instancia_fin', 'Salida de '.$nombre, $detalle);
        }

        foreach ($caso->otrosSeguimientos as $otro) {
            $nombre = $otro->nombre_instancia ?: 'otra instancia';
            $detalle = collect([$otro->num_caso ? 'Caso '.$otro->num_caso : null, $otro->detalles])->filter()->join(' · ');
            $agregar($otro->fecha_inicio, 'instancia', 'Ingreso a '.$nombre, $detalle);
            $agregar($otro->fecha_fin, 'instancia_fin', 'Salida de '.$nombre, $detalle);
        }

        foreach ($caso->seguimientosAgendados as $agendado) {
            $agregar($agendado->fecha, 'agendado', 'Seguimiento agendado',
                collect([$agendado->detalles, $agendado->personal ? 'Responsable: '.$agendado->personal->nombre_completo : null])->filter()->join(' · '),
                $agendado->hora);
        }

        foreach ($caso->informesCierre as $informe) {
            $agregar($informe->fecha_cierre ?? $informe->created_at, 'cierre', 'Caso cerrado',
                collect([$informe->resultado ? 'Resultado: '.$informe->resultado : null, $informe->nota_netley])->filter()->join(' · '));
        }

        // Vencimiento estimado del proceso (si todavía no pasó).
        if ($caso->fecha_fin && $caso->fecha_fin->isAfter(today())) {
            $agregar($caso->fecha_fin, 'vencimiento', 'Vencimiento estimado del proceso');
        }

        return $eventos
            ->sortBy(fn (array $evento) => $evento['fecha']->format('Y-m-d').' '.($evento['hora'] ?? '00:00'))
            ->values();
    }

    /** @return array{0: string, 1: string} ícono y color de cada tipo de evento */
    protected static function estilo(string $tipo): array
    {
        return match ($tipo) {
            'consulta' => ['heroicon-o-chat-bubble-left-right', '#64748b'],
            'cita' => ['heroicon-o-calendar-days', '#0ea5e9'],
            'inicio' => ['heroicon-o-scale', '#1a2537'],
            'pago' => ['heroicon-o-banknotes', '#16a34a'],
            'correccion' => ['heroicon-o-pencil-square', '#ea580c'],
            'documento' => ['heroicon-o-document-text', '#7c3aed'],
            'seguimiento' => ['heroicon-o-clipboard-document-list', '#d97706'],
            'instancia' => ['heroicon-o-building-library', '#2563eb'],
            'instancia_fin' => ['heroicon-o-building-library', '#94a3b8'],
            'agendado' => ['heroicon-o-clock', '#0891b2'],
            'cierre' => ['heroicon-o-lock-closed', '#dc2626'],
            'vencimiento' => ['heroicon-o-flag', '#94a3b8'],
            default => ['heroicon-o-information-circle', '#64748b'],
        };
    }
}
