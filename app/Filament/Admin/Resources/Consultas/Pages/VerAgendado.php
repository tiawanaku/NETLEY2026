<?php

namespace App\Filament\Admin\Resources\Consultas\Pages;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Consultas\Actions\AgendarCitaAction;
use App\Filament\Admin\Resources\Consultas\Actions\AscenderCasoAction;
use App\Filament\Admin\Resources\Consultas\Actions\ReactivarConsultaAction;
use App\Filament\Admin\Resources\Consultas\ConsultaResource;
use App\Filament\Admin\Resources\Consultas\Schemas\ConsultaForm;
use App\Models\Cita;
use App\Models\Consulta;
use App\Support\FormularioCita;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Ficha que se abre al hacer clic en una fila de Consultas > "Agendados"
 * (ver ConsultasTable). Arranca como una copia de EditConsulta a propósito:
 * el usuario pidió que sea una vista aparte para poder seguir
 * modificándola sin afectar la ficha normal de "Nuevas consultas"/
 * "Consultas cerradas" (EditConsulta), aunque por ahora se vean iguales.
 */
class VerAgendado extends EditRecord
{
    protected static string $resource = ConsultaResource::class;

    public bool $editando = false;

    /** Opciones del selector de "Cerrar consulta" que no son un EstadoConsulta directo. */
    public const OPCION_CLIENTE_EJECUTIVO = 'cliente_ejecutivo';

    public const OPCION_CITA_ANULADA = 'cita_anulada';

    public const OPCION_REAGENDAR = 'reagendar';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->editando = request()->boolean('editar') && auth()->user()?->puede('editar');

        // Motivo "Reagendar" (ver guardarCierre()): monta "agendarCita" acá,
        // de entrada en la carga de la página, en vez de anidado dentro de
        // la propia acción "guardarCierreConsulta" — montada así, Filament
        // no engancha bien el botón Guardar del modal (el click no llega al
        // backend) porque guardarCierreConsulta es una acción de un
        // componente de schema (la Section "Cerrar consulta"), y anidar dos
        // niveles de acciones dentro de ese tipo de acción no funciona.
        if ($citaId = request()->integer('reagendarCitaId')) {
            $this->mountAction('agendarCita', ['copiarDeCitaId' => $citaId]);
        }
    }

    /**
     * Método de acción de página (no en getHeaderActions(), no es un botón):
     * así Filament la resuelve ya desde mount() -- ver más arriba -- sin
     * depender de que el caché de acciones del encabezado ya esté armado.
     */
    protected function agendarCitaAction(): Action
    {
        return AgendarCitaAction::make();
    }

    public function getTitle(): string
    {
        return 'N° '.$this->getRecord()->getKey();
    }

    /**
     * Sin la sección "Seguimiento" acá (a pedido): el resto del formulario
     * es igual al de EditConsulta, más "Cerrar consulta" al final (ver
     * cerrarConsultaSection()).
     */
    public function form(Schema $schema): Schema
    {
        return ConsultaForm::configure($schema, after: [$this->cerrarConsultaSection()], conSeguimiento: false);
    }

    /**
     * @return array<string, string>
     */
    public static function opcionesMotivoCierre(): array
    {
        return [
            self::OPCION_CLIENTE_EJECUTIVO => 'Cliente ejecutivo',
            self::OPCION_REAGENDAR => 'Reagendar',
            EstadoConsulta::SoloConsulta->value => 'Solo consulta',
            EstadoConsulta::NoContesta->value => 'No contesta',
            EstadoConsulta::NoAsistio->value => 'No asistió',
            self::OPCION_CITA_ANULADA => 'Cita anulada',
            EstadoConsulta::RemitirPsicologia->value => 'También requiere psicología',
            EstadoConsulta::RemitirSocial->value => 'También requiere trabajo social',
        ];
    }

    /**
     * Campos de Filament de verdad (Select/Textarea), no maquetados a mano,
     * para que se vean exactamente igual que el resto del formulario.
     * "Guardar"/"Cancelar" van en el pie de la sección (->footer()) y leen
     * el valor actual de los campos con Get, sin pasar por el botón
     * "Guardar" general de la ficha (ver guardarCierre()).
     */
    protected function cerrarConsultaSection(): Section
    {
        if (! $this->puedeCerrar()) {
            return Section::make('Cerrar consulta')
                ->schema([
                    Placeholder::make('cerrar_consulta_no_disponible')
                        ->hiddenLabel()
                        ->content($this->getRecord()->caso_id !== null
                            ? 'Ya es Cliente Ejecutivo, no se puede volver a cerrar desde acá.'
                            : 'Esta consulta ya está cerrada, no se puede volver a cerrar desde acá.'),
                ])
                ->columns(1);
        }

        return Section::make('Cerrar consulta')
            ->columns(1)
            ->schema([
                Select::make('cierre_motivo')
                    ->label('Motivo')
                    ->options(self::opcionesMotivoCierre())
                    ->placeholder('— Sin cambios —')
                    ->native(false)
                    ->default(fn () => $this->motivoInicial())
                    ->live(),
                Textarea::make('cierre_detalle')
                    ->label('Detalle')
                    ->rows(3),
            ])
            ->footerActions([
                Action::make('guardarCierreConsulta')
                    ->label('Guardar')
                    ->color('primary')
                    // "ascenderCaso" (motivo "Cliente ejecutivo") se monta
                    // DESDE DENTRO de esta misma acción; sin registrarla acá
                    // como hija, Filament no la encuentra en ese contexto
                    // anidado y mountAction() no hace nada. "agendarCita"
                    // (motivo "Reagendar") NO se registra acá a propósito:
                    // ver guardarCierre() y mount().
                    ->registerModalActions([AscenderCasoAction::make()])
                    ->action(fn (Get $get) => $this->guardarCierre($get('cierre_motivo'), $get('cierre_detalle'))),
                Action::make('cancelarCierreConsulta')
                    ->label('Cancelar')
                    ->color('gray')
                    // Descarta lo elegido (y, en edición, lo modificado en la
                    // ficha) y vuelve al listado de Agendados.
                    ->action(fn () => $this->redirect(ConsultaResource::getUrl('index').'?tab=agendados')),
            ])
            ->footerActionsAlignment(Alignment::End);
    }

    protected function motivoInicial(): ?string
    {
        $record = $this->getRecord();

        if ($record->caso_id !== null) {
            return self::OPCION_CLIENTE_EJECUTIVO;
        }

        $estadoActual = $record->estado?->value;

        return array_key_exists($estadoActual, self::opcionesMotivoCierre()) ? $estadoActual : null;
    }

    /** Documento (por índice) de la cita vigente de ESTA consulta. */
    protected function documentoCita(array $arguments): array
    {
        $documento = ($this->citaVigente()?->documentos ?? [])[$arguments['indice'] ?? -1] ?? null;

        abort_unless(is_array($documento) && filled($documento['ruta'] ?? null), 404);

        return $documento;
    }

    /** La cita vigente (no anulada, la más reciente) de esta consulta, o null si no tiene ninguna. */
    protected function citaVigente(): ?Cita
    {
        return $this->getRecord()->citas()->where('anulada', false)->orderByDesc('fecha')->orderByDesc('hora')->first();
    }

    /** Mismo criterio que CerrarConsultaAction: solo si sigue abierta y no se ascendió a caso. */
    protected function puedeCerrar(): bool
    {
        return in_array($this->getRecord()->estado, EstadoConsulta::abiertos(), true)
            && $this->getRecord()->caso_id === null;
    }

    /**
     * Según la opción elegida en "Cerrar consulta" (ver cerrarConsultaSection()):
     * - Cliente ejecutivo: abre el asistente completo (no se puede ascender
     *   con un solo campo).
     * - Reagendar: anula la cita vigente y abre "Agendar" para cargar una
     *   nueva, con los mismos abogado(s) y datos de comisión pero sin
     *   fecha/hora (se vuelven a elegir).
     * - Cita anulada: anula la cita vigente, exige un Detalle (motivo de la
     *   anulación) y deja un registro en el historial de la cita.
     * - Un motivo de cierre: cierra la consulta con ese estado.
     * - Nada elegido: no hace nada.
     */
    protected function guardarCierre(?string $motivoSeleccionado, ?string $detalle): void
    {
        abort_unless(auth()->user()?->puede('editar'), 403);

        if (! $this->puedeCerrar()) {
            return;
        }

        $record = $this->getRecord();
        $observacion = trim((string) $detalle);

        // En edición (?editar=1) este mismo Guardar guarda también todo lo
        // modificado en la ficha (ver getFormActions()); sin motivo elegido,
        // solo eso.
        if (blank($motivoSeleccionado)) {
            if ($this->editando) {
                $this->guardarEdicion();
                Notification::make()->title('Guardado')->success()->send();
                $this->redirect($this->urlFicha());
            }

            return;
        }

        if ($motivoSeleccionado === self::OPCION_CLIENTE_EJECUTIVO) {
            $this->guardarEdicion();
            $this->mountAction('ascenderCaso');

            return;
        }

        if ($motivoSeleccionado === self::OPCION_REAGENDAR) {
            $vigente = $this->citaVigente();

            if (! $vigente) {
                Notification::make()->title('No hay ninguna cita vigente para reagendar')->warning()->send();

                return;
            }

            // A diferencia del botón "Reagendar" del encabezado (que solo
            // edita la cita vigente en el sitio), este motivo anula la cita
            // actual y abre "Agendar" para cargar una nueva, con los mismos
            // abogado(s) y datos de comisión — pero la fecha/hora quedan en
            // blanco, las vuelve a elegir la persona (ver
            // AgendarCitaAction::make(), argumento 'copiarDeCitaId').
            $this->guardarEdicion();
            $vigente->update([
                'anulada' => true,
                'observaciones' => $vigente->observacionesCon('Anulada', 'Reagendada: se creó una nueva cita'),
            ]);

            // No $this->mountAction('agendarCita', ...) acá: esta acción
            // (guardarCierreConsulta) es la de un componente de schema (la
            // Section "Cerrar consulta"), y Filament no engancha bien el
            // botón Guardar de un modal anidado dos niveles dentro de ese
            // tipo de acción (el clic no llega al backend). En cambio, se
            // recarga la página con el id de la cita anulada, y mount() la
            // monta ahí, a nivel de página (ver getHeaderActions()).
            $this->redirect(ConsultaResource::getUrl('verAgendado', ['record' => $record]).'?reagendarCitaId='.$vigente->id);

            return;
        }

        if ($motivoSeleccionado === self::OPCION_CITA_ANULADA) {
            $vigente = $this->citaVigente();

            if (! $vigente) {
                Notification::make()->title('No hay ninguna cita vigente para anular')->warning()->send();

                return;
            }

            if ($observacion === '') {
                Notification::make()->title('Escribe en Detalle el motivo de la anulación antes de guardar')->warning()->send();

                return;
            }

            $this->guardarEdicion();
            $vigente->update([
                'anulada' => true,
                'observaciones' => $vigente->observacionesCon('Anulada', $observacion),
            ]);

            // La consulta se cierra con estado "Cita Anulada" (pasa a
            // Consultas cerradas, de donde se puede reactivar), y el motivo
            // queda también en su nota interna, como el resto de cierres.
            $nota = trim((string) $record->nota_interna);
            $entrada = now()->format('d/m/Y H:i')." — Cierre (Cita anulada): {$observacion}";
            $record->update([
                'estado' => EstadoConsulta::CitaAnulada,
                'nota_interna' => $nota !== '' ? "{$nota}\n{$entrada}" : $entrada,
            ]);

            Notification::make()->title('Cita anulada y consulta cerrada')->success()->send();

            $this->redirect(ConsultaResource::getUrl('index').'?tab=cerradas');

            return;
        }

        $motivo = EstadoConsulta::tryFrom((string) $motivoSeleccionado);

        if ($motivo && array_key_exists($motivo->value, self::opcionesMotivoCierre())) {
            $this->guardarEdicion();
            $nota = trim((string) $record->nota_interna);

            if ($observacion !== '') {
                $etiqueta = self::opcionesMotivoCierre()[$motivo->value];
                $entrada = now()->format('d/m/Y H:i')." — Cierre ({$etiqueta}): {$observacion}";
                $nota = $nota !== '' ? "{$nota}\n{$entrada}" : $entrada;
            }

            $record->update(['estado' => $motivo, 'nota_interna' => $nota]);

            Notification::make()->title('Consulta cerrada')->success()->send();

            // Cualquier motivo de cierre (salvo "Cliente ejecutivo",
            // manejado arriba, que abre el asistente) lleva a "Consultas
            // cerradas", donde queda ahora esta consulta.
            $this->redirect(ConsultaResource::getUrl('index').'?tab=cerradas');
        }
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = $this->getResourceBreadcrumbs();
        $breadcrumbs[] = $this->getRecordTitle();

        return $breadcrumbs;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ConsultaForm::resolverOrigen($data);
    }

    /**
     * Si la ficha está en edición, guarda lo modificado (validando el
     * formulario) sin redirigir: lo usa guardarCierre(), que después sigue
     * con el motivo de cierre elegido.
     */
    protected function guardarEdicion(): void
    {
        if (! $this->editando) {
            return;
        }

        parent::save(shouldRedirect: false, shouldSendSavedNotification: false);

        $this->guardarFormularioPdf();
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        abort_unless(auth()->user()?->puede('editar'), 403);

        parent::save($shouldRedirect, $shouldSendSavedNotification);

        $this->guardarFormularioPdf();

        $this->editando = false;

        $this->redirect($this->urlFicha());
    }

    /**
     * Cada edición guardada deja otra "foto" numerada del formulario en los
     * documentos de la cita vigente (ver FormularioCita).
     */
    protected function guardarFormularioPdf(): void
    {
        if ($vigente = $this->citaVigente()) {
            FormularioCita::generar($vigente);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            // Solo "Editar" y "Ver documentos": reagendar se hace desde
            // "Cerrar consulta" (motivo "Reagendar") y el formulario ya
            // queda como documento ("Formulario N", ver FormularioCita).
            Action::make('editarConsulta')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => ! $this->editando && auth()->user()?->puede('editar'))
                ->action(fn () => $this->redirect($this->urlFicha(editar: true))),
            Action::make('verDocumentos')
                ->label('Ver documentos')
                ->icon('heroicon-o-folder-open')
                ->color('primary')
                ->modalHeading('Documentos adjuntos')
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                // Misma tabla que "Ver documentos" de Personal: Fecha /
                // Descripción / Documento con Descargar, Editar (solo la
                // descripción) y Eliminar (documentos-cita.blade.php). Esas
                // 3 se invocan desde dentro del modal: sin registrarlas acá
                // como hijas, Filament no las encuentra en ese contexto
                // anidado y mountAction() no hace nada.
                ->registerModalActions([
                    Action::make('descargarDocumentoCita')
                        ->action(function (array $arguments) {
                            $documento = $this->documentoCita($arguments);
                            $disk = Storage::disk(config('filament.default_filesystem_disk'));

                            abort_unless($disk->exists($documento['ruta']), 404);

                            return response()->streamDownload(
                                fn () => print ($disk->get($documento['ruta'])),
                                basename($documento['ruta'])
                            );
                        }),
                    Action::make('editarDocumentoCita')
                        ->modalHeading('Editar descripción')
                        ->modalWidth('md')
                        ->modalSubmitActionLabel('Guardar')
                        ->visible(fn () => auth()->user()?->puede('editar'))
                        ->fillForm(fn (array $arguments): array => ['descripcion' => $this->documentoCita($arguments)['descripcion'] ?? null])
                        ->schema([
                            TextInput::make('descripcion')
                                ->label('Descripción')
                                ->required()
                                ->maxLength(100)
                                ->extraInputAttributes(['data-enter-nav' => 'true']),
                        ])
                        ->action(function (array $arguments, array $data): void {
                            $this->documentoCita($arguments);
                            $cita = $this->citaVigente();
                            $documentos = $cita->documentos;
                            $documentos[$arguments['indice']]['descripcion'] = $data['descripcion'];
                            $cita->update(['documentos' => $documentos]);

                            Notification::make()->title('Descripción actualizada')->success()->send();
                        }),
                    Action::make('eliminarDocumentoCita')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->modalHeading('¿Seguro que desea eliminar el documento:')
                        ->modalDescription(fn (array $arguments): HtmlString => new HtmlString(
                            '<strong style="font-size:1rem;">'.e(($this->documentoCita($arguments)['descripcion'] ?? null) ?: basename($this->documentoCita($arguments)['ruta'])).'</strong>?'
                        ))
                        ->modalSubmitActionLabel('Eliminar')
                        ->modalFooterActionsAlignment(Alignment::Start)
                        ->extraModalWindowAttributes(['class' => 'netley-eliminar-personal'])
                        ->visible(fn () => auth()->user()?->puede('editar'))
                        ->action(function (array $arguments): void {
                            $documento = $this->documentoCita($arguments);
                            $cita = $this->citaVigente();
                            $documentos = $cita->documentos;
                            unset($documentos[$arguments['indice']]);
                            $cita->update(['documentos' => array_values($documentos)]);

                            // Al reagendar, la cita nueva hereda los documentos
                            // de la anterior (mismo archivo): solo se borra del
                            // disco si ninguna otra cita lo sigue usando.
                            $enUso = $this->getRecord()->citas()->where('id', '!=', $cita->id)->get()
                                ->contains(fn (Cita $otra) => collect($otra->documentos ?? [])->contains('ruta', $documento['ruta']));

                            if (! $enUso) {
                                Storage::disk(config('filament.default_filesystem_disk'))->delete($documento['ruta']);
                            }

                            Notification::make()->title('Documento eliminado')->success()->send();
                        }),
                ])
                ->modalContent(function (): View {
                    $disk = Storage::disk(config('filament.default_filesystem_disk'));

                    return view('filament.admin.consultas.documentos-cita', [
                        // Fecha: la guardada con el documento o, en los más
                        // antiguos que no la tienen, la del archivo.
                        'documentos' => collect($this->citaVigente()?->documentos ?? [])
                            ->map(fn (array $documento) => [
                                ...$documento,
                                'fecha' => filled($documento['fecha'] ?? null)
                                    ? Carbon::parse($documento['fecha'])
                                    : (filled($documento['ruta'] ?? null) && $disk->exists($documento['ruta'])
                                        ? Carbon::createFromTimestamp($disk->lastModified($documento['ruta']))
                                        : null),
                            ])
                            ->all(),
                        'puedeEditar' => (bool) auth()->user()?->puede('editar'),
                    ]);
                }),
            ReactivarConsultaAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        // En edición, el "Guardar"/"Cancelar" de la sección "Cerrar
        // consulta" guarda/descarta también todo lo modificado (ver
        // guardarCierre()), así que no se repiten abajo. Solo quedan si esa
        // sección no tiene botones (consulta ya cerrada o cliente ejecutivo).
        if ($this->editando && $this->puedeCerrar()) {
            return [];
        }

        if ($this->editando) {
            return [
                $this->getSaveFormAction()->label('Guardar'),
                Action::make('cancelarEdicion')
                    ->label('Cancelar')
                    ->color('gray')
                    ->action(fn () => $this->redirect(ConsultaResource::getUrl('index').'?tab=agendados')),
            ];
        }

        return [
            Action::make('informeCierrePdf')
                ->label('Informe de cierre')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn (Consulta $record) => ! in_array($record->estado, EstadoConsulta::abiertos(), true))
                ->action(fn (Consulta $record) => response()->streamDownload(
                    fn () => print (Pdf::loadView('pdf.informe-cierre-consulta', [
                        'consulta' => $record->load(['respuestas.personal', 'respuestas.delito']),
                    ])->output()),
                    "informe-cierre-consulta-{$record->id}.pdf"
                )),
        ];
    }

    /** Ficha de esta consulta; con $editar abre directo en modo edición. */
    protected function urlFicha(bool $editar = false): string
    {
        $url = ConsultaResource::getUrl('verAgendado', ['record' => $this->getRecord()]);

        return $editar ? $url.'?editar=1' : $url;
    }
}
