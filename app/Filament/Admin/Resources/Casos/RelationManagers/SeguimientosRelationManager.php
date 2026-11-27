<?php

namespace App\Filament\Admin\Resources\Casos\RelationManagers;

use App\Enums\Especialidad;
use App\Filament\Admin\Pages\InformeSeguimientoDiario;
use App\Filament\Admin\Pages\SeguimientoDiario;
use App\Filament\Admin\Support\DatosInstancia;
use App\Filament\Admin\Support\EtapasProceso;
use App\Filament\Admin\Support\SeguimientoProceso;
use App\Models\ActuadoCaso;
use App\Models\Caso;
use App\Models\SeguimientoCaso;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Seguimiento del proceso (ver Support\SeguimientoProceso): "Registrar
 * seguimiento" planifica los actuados a realizar y "Registrar actuados
 * realizados" (Informe) los concluye. Arriba, la barra de etapas y las
 * fichas Informes pendientes / Fs. Ministerio Público / Fs. Juzgado / Alarma.
 *
 * Desde Seguimiento › Seguimiento diario / Informe seguimiento diario se
 * llega con el formulario ya abierto (argumento "volver"): al guardar, se
 * vuelve a esa lista para seguir con el próximo cliente.
 */
class SeguimientosRelationManager extends RelationManager
{
    protected static string $relationship = 'seguimientos';

    protected static ?string $title = 'Seguimiento del proceso';

    protected static ?string $modelLabel = 'seguimiento';

    protected function caso(): Caso
    {
        /** @var Caso */
        return $this->getOwnerRecord();
    }

    /** Tras guardar: vuelve a la lista de donde vino, si vino de una. */
    protected function volver(array $arguments): void
    {
        match ($arguments['volver'] ?? null) {
            'diario' => $this->redirect(SeguimientoDiario::getUrl()),
            'informe' => $this->redirect(InformeSeguimientoDiario::getUrl()),
            default => null,
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('etapa_proceso')
            ->header(function () {
                $caso = $this->caso();

                return view('filament.admin.casos.seguimiento-resumen', [
                    'acciones' => array_filter($this->getTable()->getHeaderActions(), fn ($a) => $a->isVisible()),
                    'etapas' => EtapasProceso::de($caso),
                    'ultimo' => SeguimientoProceso::ultimo($caso),
                    // Actuados no concluidos, el más próximo primero:
                    // fichas "Informes pendientes" y "Alarma".
                    'pendientes' => $caso->actuados()->pendientes()
                        ->orderByRaw('fecha is null')->orderBy('fecha')->orderBy('hora')->orderBy('id')->get(),
                    'esPenal' => $caso->especialidad === Especialidad::Penal,
                    'fiscalia' => DatosInstancia::fiscalia($caso),
                    'juzgado' => DatosInstancia::juzgado($caso),
                ]);
            })
            ->modifyQueryUsing(fn ($query) => $query->with(['personal', 'actuados', 'actuadosConcluidos']))
            ->columns([
                TextColumn::make('fecha_seguimiento')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('tipo_registro')
                    ->label('Registro')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'informe' ? 'Actuados realizados' : 'Seguimiento')
                    ->color(fn (?string $state) => $state === 'informe' ? 'success' : 'info'),
                TextColumn::make('etapa_proceso')->label('Etapa')->wrap()->placeholder('—')
                    ->description(fn (SeguimientoCaso $r) => $r->instancia),
                TextColumn::make('abogado_nombre')->label('Abogada / procuradora')->placeholder('—'),
                TextColumn::make('observaciones')
                    ->label('Actuados')
                    ->wrap()
                    ->formatStateUsing(fn (SeguimientoCaso $r) => self::actuadosHtml($r)),
            ])
            ->defaultSort(fn ($query) => $query->orderByDesc('fecha_seguimiento')->orderByDesc('id'))
            ->emptyStateHeading('Sin seguimientos todavía')
            ->emptyStateDescription('Usa "Registrar seguimiento" para anotar los actuados a realizar, y "Registrar actuados realizados" para marcar lo que se concluyó.')
            ->headerActions([
                Action::make('registrarSeguimiento')
                    ->label('Registrar seguimiento')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Registrar seguimiento diario')
                    ->modalWidth(Width::SixExtraLarge)
                    ->schema(fn () => SeguimientoProceso::formularioSeguimiento($this->caso()))
                    ->modalSubmitActionLabel('Guardar')
                    ->modalCancelActionLabel('Cancelar')
                    ->action(function (array $data, array $arguments): void {
                        SeguimientoProceso::guardarSeguimiento($this->caso(), $data);
                        Notification::make()->title('Seguimiento registrado')->success()->send();
                        $this->volver($arguments);
                    }),
                Action::make('registrarInforme')
                    ->label('Registrar actuados realizados')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->modalHeading('Informe del proceso: registrar actuados realizados')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->schema(fn () => SeguimientoProceso::formularioInforme($this->caso()))
                    ->fillForm(fn () => SeguimientoProceso::datosInforme($this->caso()))
                    ->modalSubmitActionLabel('Guardar')
                    ->modalCancelActionLabel('Cancelar')
                    ->action(function (array $data, array $arguments): void {
                        SeguimientoProceso::guardarInforme($this->caso(), $data);
                        Notification::make()->title('Informe registrado')->success()->send();
                        $this->volver($arguments);
                    }),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                DeleteAction::make()
                    ->modalDescription('Se borra este registro y los actuados que planificó. Los actuados concluidos en un informe vuelven a quedar pendientes si se borra ese informe.')
                    ->visible(fn () => auth()->user()?->puede('eliminar'))
                    ->before(function (SeguimientoCaso $record): void {
                        // Borrar un informe: lo que concluyó vuelve a quedar pendiente.
                        $record->actuadosConcluidos()->update(['concluido' => false, 'concluido_at' => null, 'informe_id' => null]);
                    }),
            ]);
    }

    /** Lista de actuados de un registro: planificados, o concluidos (informe). */
    protected static function actuadosHtml(SeguimientoCaso $r): HtmlString
    {
        $lista = $r->esInforme()
            ? $r->actuadosConcluidos->merge($r->actuados)->unique('id')
            : $r->actuados;

        if ($lista->isEmpty()) {
            return new HtmlString(nl2br(e($r->observaciones ?? '—')));
        }

        return new HtmlString('<ul class="netley-actuados">'.$lista->map(function (ActuadoCaso $a) use ($r) {
            $hecho = $r->esInforme() ? (int) $a->informe_id === $r->id : $a->concluido;

            return '<li class="'.($hecho ? 'netley-actuado--hecho' : ($a->vencido ? 'netley-actuado--vencido' : '')).'">'
                .'<span class="netley-actuado-marca">'.($hecho ? '✓' : '•').'</span> '
                .'<strong>'.e($a->tipo).'</strong>'
                .($a->detalle ? ' — '.e($a->detalle) : '')
                .($a->cuando ? ' <span class="netley-actuado-cuando">'.e($a->cuando).($a->alarma ? ' 🔔' : '').'</span>' : ($a->alarma ? ' 🔔' : ''))
                .'</li>';
        })->join('').'</ul>');
    }
}
