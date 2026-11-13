<?php

namespace App\Filament\Admin\Resources\Casos\Actions;

use App\Enums\EstadoCaso;
use App\Filament\Admin\Resources\Casos\Schemas\CierreCasoSchema;
use App\Filament\Admin\Resources\Clientes\ClienteResource;
use App\Filament\Admin\Support\AgendaPreview;
use App\Filament\Admin\Support\CampoHora;
use App\Filament\Admin\Support\CamposDenuncia;
use App\Filament\Admin\Support\CamposPago;
use App\Filament\Admin\Support\PlanPagoRecalculo;
use App\Models\Caso;
use App\Models\Pago;
use App\Models\PlanPago;
use App\Support\PersonalEspecialidades;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;

/**
 * Acciones de caso compartidas entre la tabla de Casos (fila) y la ficha
 * individual (Editar caso), para que "Cerrar caso" / "Reactivar" / "Informe
 * PDF" estén visibles tanto en el listado como al abrir un caso puntual.
 */
class CasoActions
{
    /** Botón "Ver" (reemplaza el enlace de texto plano y el .btn-accion-ver legacy). */
    public static function verCliente(): Action
    {
        return Action::make('verCliente')
            ->label('Ver')
            ->icon('heroicon-o-eye')
            ->color('info')
            ->url(fn (Caso $record) => ClienteResource::getUrl('edit', ['record' => $record->cliente_id]));
    }

    public static function cerrar(): Action
    {
        return Action::make('cerrarCaso')
            ->label('Cerrar caso')
            ->icon('heroicon-o-archive-box')
            ->color('danger')
            ->visible(fn (Caso $record) => $record->estado !== EstadoCaso::Cerrado)
            ->schema(CierreCasoSchema::make())
            ->action(function (Caso $record, array $data): void {
                $record->informesCierre()->create([
                    'cliente_id' => $record->cliente_id,
                    'creado_por' => auth()->user()?->username,
                    ...$data,
                ]);
                $record->update(['estado' => EstadoCaso::Cerrado]);

                Notification::make()->title('Caso cerrado')->success()->send();
            });
    }

    /**
     * Elimina el caso (borrado lógico): pasa a "Casos eliminados", con sus
     * pagos, documentos y seguimientos intactos, y se puede restaurar.
     */
    public static function eliminar(): Action
    {
        return Action::make('eliminarCaso')
            ->label('Eliminar caso')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (Caso $record) => ! $record->trashed() && auth()->user()?->puede('eliminar'))
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(fn (Caso $record) => '¿Eliminar el caso N° '.$record->id.'?')
            ->modalDescription('El caso pasa a "Casos eliminados" y se puede restaurar desde ahí. Sus pagos, documentos y seguimientos no se borran.')
            ->modalWidth('lg')
            ->fillForm(fn (): array => \App\Filament\Admin\Support\Eliminacion::valoresIniciales())
            ->schema(\App\Filament\Admin\Support\Eliminacion::campos())
            ->modalSubmitActionLabel('Eliminar')
            ->modalCancelActionLabel('Cancelar')
            ->modalFooterActionsAlignment(\Filament\Support\Enums\Alignment::Start)
            ->extraModalWindowAttributes(['class' => 'netley-eliminar-personal'])
            ->action(function (Caso $record, array $data, $livewire): void {
                \App\Filament\Admin\Support\Eliminacion::eliminar($record, $data);
                Notification::make()->title('Caso eliminado')->body('Lo encuentras en "Casos eliminados".')->success()->send();

                // Desde la ficha del cliente (tabla de Casos) se queda ahí.
                if ($livewire instanceof \Filament\Resources\RelationManagers\RelationManager) {
                    return;
                }

                $livewire->redirect(\App\Filament\Admin\Resources\Casos\CasoResource::getUrl('index').'?tab=eliminados');
            });
    }

    /** Documento de respaldo que se subió al eliminar el caso. */
    public static function descargarDocumentoEliminacion(): Action
    {
        return Action::make('descargarDocumentoEliminacion')
            ->label('Descargar documento')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (Caso $record) => $record->trashed() && filled($record->documento_eliminacion))
            ->action(fn (Caso $record) => \App\Filament\Admin\Support\Eliminacion::descargar($record->documento_eliminacion, 'eliminacion-caso-'.$record->id));
    }

    /** Devuelve un caso eliminado a su lista normal. */
    public static function restaurar(): Action
    {
        return Action::make('restaurarCaso')
            ->label('Restaurar')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('success')
            ->visible(fn (Caso $record) => $record->trashed() && auth()->user()?->puede('eliminar'))
            ->requiresConfirmation()
            ->modalHeading(fn (Caso $record) => '¿Restaurar el caso N° '.$record->id.'?')
            ->modalSubmitActionLabel('Restaurar')
            ->modalCancelActionLabel('Cancelar')
            ->modalFooterActionsAlignment(\Filament\Support\Enums\Alignment::Start)
            ->extraModalWindowAttributes(['class' => 'netley-eliminar-personal'])
            ->action(function (Caso $record, $livewire): void {
                $record->restore();
                Notification::make()->title('Caso restaurado')->success()->send();
                $livewire->redirect(\App\Filament\Admin\Resources\Casos\CasoResource::getUrl('edit', ['record' => $record]));
            });
    }

    public static function reactivar(): Action
    {
        return Action::make('reactivarCaso')
            ->label('Reactivar')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (Caso $record) => $record->estado === EstadoCaso::Cerrado)
            ->requiresConfirmation()
            ->action(function (Caso $record): void {
                $record->update(['estado' => EstadoCaso::Activo]);
                Notification::make()->title('Caso reactivado')->success()->send();
            });
    }

    public static function informeCierrePdf(): Action
    {
        return Action::make('informeCierrePdf')
            ->label('Informe de cierre PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->visible(fn (Caso $record) => $record->estado === EstadoCaso::Cerrado && $record->informeCierre)
            ->action(fn (Caso $record) => response()->streamDownload(
                fn () => print(Pdf::loadView('pdf.informe-cierre', ['informe' => $record->informeCierre->load('caso.cliente')])->output()),
                "informe-cierre-caso-{$record->id}.pdf"
            ));
    }

    /** Imprime, en cualquier estado del caso, un resumen de una página con
     * los datos del cliente, la ficha del caso y sus finanzas — a diferencia
     * de informeCierrePdf(), no requiere que el caso esté cerrado.
     */
    public static function informeRapido(): Action
    {
        return Action::make('informeRapido')
            ->label('Resumen del Caso')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->action(fn (Caso $record) => response()->streamDownload(
                fn () => print(Pdf::loadView('pdf.informe-rapido-caso', [
                    'caso' => $record->load(['cliente', 'personal', 'delito', 'delitosDenunciados', 'planesPago', 'pagos']),
                ])->output()),
                "informe-rapido-caso-{$record->id}.pdf"
            ));
    }

    /** Agenda una cita ligada directamente al caso (no a una consulta), con
     * la misma vista previa de agenda de 14 días que en Consultas.
     */
    /**
     * El caso de una acción: el registro de la fila/ficha o, desde la
     * pestaña "Citas" de la ficha (CitasRelationManager, botón de la
     * cabecera, sin registro), el caso dueño de esa pestaña.
     */
    protected static function casoDe(mixed $livewire, mixed $record): Caso
    {
        return $record instanceof Caso ? $record : $livewire->getOwnerRecord();
    }

    public static function agendarCita(): Action
    {
        return Action::make('agendarCitaCaso')
            ->label('Agendar cita')
            ->icon('heroicon-o-calendar-days')
            ->color('info')
            ->schema([
                Select::make('personal')
                    ->label('Abogado(s) asignado(s)')
                    ->options(fn () => PersonalEspecialidades::personalOptionsPlain(null, [], 'Abogado'))
                    ->multiple()
                    ->searchable()
                    ->live(),
                Placeholder::make('agenda_personal')
                    ->label('Agenda (próximos 14 días)')
                    ->content(fn (Get $get) => AgendaPreview::render($get('personal')))
                    ->columnSpanFull(),
                // El id fijo lo usa agenda-preview.blade.php para escribir la
                // fecha directo al hacer clic en un día del mini-calendario.
                DatePicker::make('fecha')->required()->default(now())->extraInputAttributes(['id' => 'agendar-cita-fecha']),
                CampoHora::make('hora')->label('Hora')->required(),
                Textarea::make('detalle')->rows(3),
                // Se guardan en el caso (no en la cita).
                CamposDenuncia::denunciado()
                    ->default(fn ($livewire, $record = null) => self::casoDe($livewire, $record)->denunciado),
                CamposDenuncia::denuncias()
                    ->default(fn ($livewire, $record = null) => CamposDenuncia::ids(self::casoDe($livewire, $record))),
            ])
            ->action(function (array $data, $livewire, $record = null): void {
                $record = self::casoDe($livewire, $record);

                DB::transaction(function () use ($record, $data): void {
                    $cita = $record->citas()->create([
                        'fecha' => $data['fecha'],
                        'hora' => $data['hora'],
                        'detalle' => $data['detalle'] ?? null,
                    ]);

                    if (filled($data['personal'] ?? null)) {
                        $cita->personal()->sync($data['personal']);
                    }

                    $record->update(['denunciado' => filled($data['denunciado'] ?? null) ? $data['denunciado'] : null]);
                    CamposDenuncia::sincronizar($record, $data['denuncias'] ?? []);
                });

                CamposDenuncia::refrescarFicha($livewire, $record);

                Notification::make()->title('Cita agendada')->success()->send();
            });
    }

    /** Registra un pago contra la cuota pendiente elegida del plan de pagos
     * (o un pago libre si no hay ninguna pendiente), actualizando el saldo
     * del caso automáticamente vía PagoObserver.
     */
    public static function realizarPago(): Action
    {
        return Action::make('realizarPago')
            ->label('Realizar pago')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Caso $record) => $record->planesPago()->where('estado', 'pendiente')->exists())
            ->schema([
                Select::make('plan_pago_id')
                    ->label('Cuota a pagar')
                    ->options(fn (Caso $record) => $record->planesPago()
                        ->where('estado', 'pendiente')
                        ->orderBy('numero')
                        ->get()
                        ->mapWithKeys(fn (PlanPago $p) => [
                            $p->id => 'Cuota '.$p->numero.' — Bs. '.number_format($p->monto, 2).' — '.$p->fecha->format('d/m/Y'),
                        ]))
                    ->default(fn (Caso $record) => $record->planesPago()->where('estado', 'pendiente')->orderBy('numero')->value('id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, $set) => $set('monto', PlanPago::find($state)?->monto)),
                TextInput::make('monto')
                    ->numeric()
                    ->prefix('Bs.')
                    ->required()
                    ->minValue(0.01)
                    ->maxValue(fn (Caso $record) => (float) $record->saldo)
                    ->validationMessages(['max' => 'No puede pagar más del saldo pendiente (Bs. :max).'])
                    ->default(fn (Caso $record) => $record->planesPago()->where('estado', 'pendiente')->orderBy('numero')->value('monto')),
                ...CamposPago::components(),
                DatePicker::make('fecha_pago')->default(now())->required(),
                Select::make('sucursal')
                    ->options(['LA PAZ' => 'La Paz', 'SANTA CRUZ' => 'Santa Cruz', 'COCHABAMBA' => 'Cochabamba'])
                    ->default('LA PAZ')
                    ->required(),
            ])
            ->action(function (Caso $record, array $data): void {
                $plan = $record->planesPago()->find($data['plan_pago_id']);
                $monto = (float) $data['monto'];

                Pago::create([
                    'caso_id' => $record->id,
                    'cliente_id' => $record->cliente_id,
                    'monto' => $monto,
                    ...CamposPago::datos($data),
                    'fecha_pago' => $data['fecha_pago'],
                    'nro_cuota' => $plan?->numero ?? 0,
                    'nro_recibo' => ((int) Pago::max('nro_recibo')) + 1,
                    'sucursal' => $data['sucursal'],
                    'registrado_por' => auth()->user()?->username,
                ]);

                $plan?->update(['estado' => 'pagado']);

                // Si se pagó de más respecto a lo que correspondía a esta
                // cuota, el excedente se descuenta del saldo y el plan de
                // pagos restante se recalcula en partes iguales.
                if ($plan && $monto > (float) $plan->monto) {
                    PlanPagoRecalculo::redistribuir($record);
                }

                Notification::make()->title('Pago registrado')->success()->send();
            });
    }
}
