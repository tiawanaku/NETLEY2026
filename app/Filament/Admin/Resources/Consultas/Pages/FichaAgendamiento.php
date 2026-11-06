<?php

namespace App\Filament\Admin\Resources\Consultas\Pages;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Consultas\Actions\AnularCitaAction;
use App\Filament\Admin\Resources\Consultas\Actions\AscenderCasoAction;
use App\Filament\Admin\Resources\Consultas\Actions\CerrarConsultaAction;
use App\Filament\Admin\Resources\Consultas\Actions\ConfirmarCitaAction;
use App\Filament\Admin\Resources\Consultas\Actions\ReactivarConsultaAction;
use App\Filament\Admin\Resources\Consultas\Actions\ReagendarCitaAction;
use App\Filament\Admin\Resources\Consultas\ConsultaResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;

/**
 * Ficha de agendamiento de una consulta: la vista que se abre al hacer clic
 * en la fila o en "Ver" desde la pestaña Agendados (ver ConsultasTable).
 * Reproduce el formulario de papel "Consultas directas NETLEY" (forma de
 * ingreso, datos del cliente, tipo de proceso, agendar/reprogramar y cerrar
 * caso), reusando las acciones y datos que ya existen en el sistema en vez
 * de duplicarlos.
 */
class FichaAgendamiento extends ViewRecord
{
    protected static string $resource = ConsultaResource::class;

    /** Opciones del selector de "Cerrar caso" que no son un EstadoConsulta directo. */
    public const OPCION_CLIENTE_EJECUTIVO = 'cliente_ejecutivo';

    public const OPCION_CITA_ANULADA = 'cita_anulada';

    /** Motivo elegido en el selector interactivo de "Cerrar caso" (null = sin seleccionar). */
    public ?string $motivoCierre = null;

    /**
     * Texto libre de "Observación" del cierre de caso: campo propio e
     * independiente (citas.observacion_cierre), distinto de la "Nota"
     * (citas.detalle) que se llena al agendar la cita. Siempre empieza
     * vacío al abrir la ficha (no se precarga ningún valor anterior); si
     * la cita no se realizó (no hay cita vigente) queda vacío.
     */
    public ?string $observacionCierre = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();

        $this->motivoCierre = $this->motivoInicial();
        $this->observacionCierre = null;
    }

    protected function motivoInicial(): ?string
    {
        $record = $this->getRecord();

        if ($record->caso_id !== null) {
            return self::OPCION_CLIENTE_EJECUTIVO;
        }

        $estadoActual = $record->estado?->value;

        return array_key_exists($estadoActual, CerrarConsultaAction::motivos()) ? $estadoActual : null;
    }

    /** La cita vigente (no anulada, la más reciente) de esta consulta, o null si no tiene ninguna. */
    protected function citaVigente(): ?\App\Models\Cita
    {
        return $this->getRecord()->citas()->where('anulada', false)->orderByDesc('fecha')->orderByDesc('hora')->first();
    }

    public function getTitle(): string
    {
        return 'Agendamiento — N° '.$this->getRecord()->getKey();
    }

    /** Sin encabezado visible en la página (solo queda en la pestaña del navegador y en la migaja de pan). */
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = $this->getResourceBreadcrumbs();
        $breadcrumbs[] = $this->getRecordTitle();

        return $breadcrumbs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imprimir')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(fn () => $this->imprimir()),
            Action::make('verFichaCompleta')
                ->label('Ver ficha completa')
                ->icon('heroicon-o-identification')
                ->color('gray')
                ->url(fn (): string => ConsultaResource::getUrl('edit', ['record' => $this->getRecord()])),
            AscenderCasoAction::make(),
            CerrarConsultaAction::make(),
            ReactivarConsultaAction::make(),
            // Sin botón propio: se invocan desde la lista de citas de abajo
            // (ver resources/views/filament/admin/consultas/citas-agendadas.blade.php).
            AnularCitaAction::make()->visible(false),
            ReagendarCitaAction::make()->visible(false),
            ConfirmarCitaAction::make()->visible(false),
        ];
    }

    /**
     * Reproduce el formulario de papel completo como una sola vista (ver
     * resources/views/filament/admin/consultas/ficha-agendamiento.blade.php)
     * en vez de armarlo con Section/Placeholder de Filament, para poder
     * calcar su formato (cabeceras oscuras, casillas, cuadrícula).
     */
    public function content(Schema $schema): Schema
    {
        $record = $this->getRecord();

        return $schema->components([
            Placeholder::make('ficha_agendamiento_display')
                ->hiddenLabel()
                ->content(fn (): View => view('filament.admin.consultas.ficha-agendamiento', [
                    'consulta' => $record,
                    'citas' => $record->citas()->with('personal')->orderBy('fecha')->orderBy('hora')->get(),
                    'puedeEditar' => auth()->user()?->puede('editar') ?? false,
                    'puedeCerrar' => $this->puedeCerrar(),
                    'motivos' => CerrarConsultaAction::motivos(),
                ]))
                ->columnSpanFull(),
        ]);
    }

    /** Mismo criterio que CerrarConsultaAction: solo si sigue abierta y no se ascendió a caso. */
    protected function puedeCerrar(): bool
    {
        return in_array($this->getRecord()->estado, EstadoConsulta::abiertos(), true)
            && $this->getRecord()->caso_id === null;
    }

    /**
     * Guarda lo elegido en el selector interactivo de "Cerrar caso" (ver
     * blade) junto con la Observación (campo propio del cierre, se guarda
     * en citas.observacion_cierre, independiente de la "Nota"). Según la
     * opción:
     * - Cliente ejecutivo: guarda la observación y abre el asistente
     *   completo (no se puede ascender con un solo campo).
     * - Cita anulada: anula la cita vigente, dejando además un registro en
     *   su historial (citas.observaciones), igual que AnularCitaAction.
     * - Un motivo de cierre: cierra la consulta con ese estado.
     * - Nada elegido: solo guarda la observación en la cita vigente.
     */
    public function guardarCierre(): void
    {
        abort_unless(auth()->user()?->puede('editar'), 403);

        if (! $this->puedeCerrar()) {
            return;
        }

        $record = $this->getRecord();
        $vigente = $this->citaVigente();
        $observacion = trim((string) $this->observacionCierre);

        if ($vigente && $vigente->observacion_cierre !== $observacion) {
            $vigente->update(['observacion_cierre' => $observacion]);
        }

        if ($this->motivoCierre === self::OPCION_CLIENTE_EJECUTIVO) {
            $this->mountAction('ascenderCaso');

            return;
        }

        if ($this->motivoCierre === self::OPCION_CITA_ANULADA) {
            if (! $vigente) {
                Notification::make()->title('No hay ninguna cita vigente para anular')->warning()->send();

                return;
            }

            if ($observacion === '') {
                Notification::make()->title('Escribe en Observación el motivo de la anulación antes de guardar')->warning()->send();

                return;
            }

            $vigente->update([
                'anulada' => true,
                'observaciones' => $vigente->observacionesCon('Anulada', $observacion),
            ]);

            Notification::make()->title('Cita anulada')->success()->send();

            return;
        }

        $motivo = EstadoConsulta::tryFrom((string) $this->motivoCierre);

        if ($motivo && array_key_exists($motivo->value, CerrarConsultaAction::motivos())) {
            $record->update(['estado' => $motivo]);

            Notification::make()->title('Consulta cerrada')->success()->send();

            return;
        }

        Notification::make()->title('Observación guardada')->success()->send();
    }

    /** Descarta los cambios sin guardar: vuelve el selector al estado inicial y la observación a vacío. */
    public function cancelarCierre(): void
    {
        $this->motivoCierre = $this->motivoInicial();
        $this->observacionCierre = null;
    }

    /** Genera la hoja "Consultas Directas NETLEY" lista para imprimir, con el mismo formato que la de papel. */
    protected function imprimir(): mixed
    {
        $record = $this->getRecord();

        return response()->streamDownload(
            fn () => print (Pdf::loadView('pdf.ficha-agendamiento', [
                'consulta' => $record,
                'citas' => $record->citas()->with('personal')->orderBy('fecha')->orderBy('hora')->get(),
            ])->output()),
            "consulta-{$record->id}.pdf"
        );
    }
}
