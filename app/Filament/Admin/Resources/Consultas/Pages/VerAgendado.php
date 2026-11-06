<?php

namespace App\Filament\Admin\Resources\Consultas\Pages;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Consultas\Actions\AgendarCitaAction;
use App\Filament\Admin\Resources\Consultas\Actions\AscenderCasoAction;
use App\Filament\Admin\Resources\Consultas\Actions\ReactivarConsultaAction;
use App\Filament\Admin\Resources\Consultas\Actions\ReagendarCitaAction;
use App\Filament\Admin\Resources\Consultas\ConsultaResource;
use App\Filament\Admin\Resources\Consultas\Schemas\ConsultaForm;
use App\Models\Cita;
use App\Models\Consulta;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * "Guardar cambios" general de la ficha (ver guardarCierre()).
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
                    // "ascenderCaso" (motivo "Cliente ejecutivo") y
                    // "agendarCita" (motivo "Reagendar": anula la cita
                    // vigente y abre esta para cargar una nueva, ver
                    // guardarCierre()) se montan DESDE DENTRO de esta misma
                    // acción; sin registrarlas acá como hijas, Filament no
                    // las encuentra en ese contexto anidado y mountAction()
                    // no hace nada.
                    ->registerModalActions([AscenderCasoAction::make(), AgendarCitaAction::make()])
                    ->action(fn (Get $get) => $this->guardarCierre($get('cierre_motivo'), $get('cierre_detalle'))),
                Action::make('cancelarCierreConsulta')
                    ->label('Cancelar')
                    ->color('gray')
                    ->action(function (Set $set): void {
                        $set('cierre_motivo', $this->motivoInicial());
                        $set('cierre_detalle', null);
                    }),
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

        if ($motivoSeleccionado === self::OPCION_CLIENTE_EJECUTIVO) {
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
            $vigente->update([
                'anulada' => true,
                'observaciones' => $vigente->observacionesCon('Anulada', 'Reagendada: se creó una nueva cita'),
            ]);

            $this->mountAction('agendarCita', ['copiarDeCitaId' => $vigente->id]);

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

            $vigente->update([
                'anulada' => true,
                'observaciones' => $vigente->observacionesCon('Anulada', $observacion),
            ]);

            Notification::make()->title('Cita anulada')->success()->send();

            $this->redirect(ConsultaResource::getUrl('index').'?tab=nuevas');

            return;
        }

        $motivo = EstadoConsulta::tryFrom((string) $motivoSeleccionado);

        if ($motivo && array_key_exists($motivo->value, self::opcionesMotivoCierre())) {
            $nota = trim((string) $record->nota_interna);

            if ($observacion !== '') {
                $etiqueta = self::opcionesMotivoCierre()[$motivo->value];
                $entrada = now()->format('d/m/Y H:i')." — Cierre ({$etiqueta}): {$observacion}";
                $nota = $nota !== '' ? "{$nota}\n{$entrada}" : $entrada;
            }

            $record->update(['estado' => $motivo, 'nota_interna' => $nota]);

            Notification::make()->title('Consulta cerrada')->success()->send();

            // Cualquier motivo salvo "Cliente ejecutivo" (manejado arriba,
            // que en cambio abre el asistente) vuelve al listado de Nuevas
            // consultas — esta ya no sigue abierta, no hay nada más que
            // hacer acá.
            $this->redirect(ConsultaResource::getUrl('index').'?tab=nuevas');
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

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        abort_unless(auth()->user()?->puede('editar'), 403);

        parent::save($shouldRedirect, $shouldSendSavedNotification);

        $this->guardarFormularioPdf();

        $this->editando = false;

        $this->redirect($this->urlFicha());
    }

    /**
     * Cada vez que se guarda una edición, queda una "foto" en PDF de cómo
     * estaba el formulario en ese momento, numerada en orden (Formulario 1,
     * Formulario 2...) y guardada junto con el resto de documentos de la
     * cita vigente — así "Ver documentos" también sirve de historial de
     * ediciones. Si no hay cita vigente (no debería pasar en esta ficha, ver
     * ConsultasTable) no se genera nada.
     */
    protected function guardarFormularioPdf(): void
    {
        $vigente = $this->citaVigente();

        if (! $vigente) {
            return;
        }

        $record = $this->getRecord();
        $documentos = $vigente->documentos ?? [];

        $numero = collect($documentos)
            ->map(fn ($documento) => preg_match('/^Formulario (\d+)$/', (string) ($documento['descripcion'] ?? ''), $coincidencia)
                ? (int) $coincidencia[1]
                : 0)
            ->max() + 1;

        $pdf = Pdf::loadView('pdf.ficha-agendamiento', [
            'consulta' => $record,
            'citas' => $record->citas()->with('personal')->orderBy('fecha')->orderBy('hora')->get(),
        ])->output();

        $ruta = 'documentos-citas/formulario-'.$record->id.'-'.$numero.'-'.Str::random(8).'.pdf';
        Storage::disk(config('filament.default_filesystem_disk'))->put($ruta, $pdf);

        $documentos[] = ['ruta' => $ruta, 'descripcion' => "Formulario {$numero}"];

        $vigente->update(['documentos' => $documentos]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('esClienteEjecutivo')
                ->label('Cliente Ejecutivo')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->disabled()
                ->visible(fn (Consulta $record) => $record->caso_id !== null),
            // Botón directo para reagendar la cita vigente, sin pasar por el
            // listado de citas (que acá no se muestra). "reagendarCita" (el
            // formulario de verdad) se monta DESDE DENTRO de esta misma
            // acción con el id de la cita vigente ya resuelto; sin
            // registrarla acá como hija, Filament no la encuentra en ese
            // contexto anidado y mountAction() no hace nada (mismo caso que
            // guardarCierreConsulta/ascenderCaso en cerrarConsultaSection()).
            Action::make('reagendarCitaVigente')
                ->label('Reagendar')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->citaVigente() !== null)
                ->registerModalActions([ReagendarCitaAction::make()])
                ->action(function (): void {
                    $vigente = $this->citaVigente();

                    if ($vigente) {
                        $this->mountAction('reagendarCita', ['cita' => $vigente->id]);
                    }
                }),
            Action::make('editarConsulta')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => ! $this->editando && auth()->user()?->puede('editar'))
                ->action(fn () => $this->redirect($this->urlFicha(editar: true))),
            Action::make('verFormulario')
                ->label('Ver formulario')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn (Consulta $record): string => ConsultaResource::getUrl('agendamiento', ['record' => $record])),
            Action::make('verDocumentos')
                ->label('Ver documentos')
                ->icon('heroicon-o-folder-open')
                ->color('primary')
                ->modalHeading('Documentos adjuntos')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                // "descargarDocumentoCita" se invoca desde dentro del modal
                // de esta misma acción (ver el blade) — sin registrarla acá
                // como hija, Filament no la encuentra en ese contexto
                // anidado y mountAction() no hace nada (mismo caso que
                // guardarCierreConsulta/ascenderCaso en cerrarConsultaSection()).
                ->registerModalActions([
                    Action::make('descargarDocumentoCita')
                        ->action(function (array $arguments) {
                            $documentos = $this->citaVigente()?->documentos ?? [];
                            $documento = $documentos[$arguments['indice']] ?? null;

                            abort_unless($documento, 404);

                            $disk = Storage::disk(config('filament.default_filesystem_disk'));

                            abort_unless($disk->exists($documento['ruta']), 404);

                            return response()->streamDownload(
                                fn () => print ($disk->get($documento['ruta'])),
                                basename($documento['ruta'])
                            );
                        }),
                ])
                ->modalContent(fn (): View => view('filament.admin.consultas.documentos-cita', [
                    'documentos' => $this->citaVigente()?->documentos ?? [],
                ])),
            ReactivarConsultaAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        if ($this->editando) {
            return [
                $this->getSaveFormAction()->label('Guardar cambios'),
                Action::make('cancelarEdicion')
                    ->label('Cancelar edición')
                    ->color('gray')
                    ->action(fn () => $this->redirect($this->urlFicha())),
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
