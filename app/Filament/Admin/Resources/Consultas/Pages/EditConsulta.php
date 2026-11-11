<?php

namespace App\Filament\Admin\Resources\Consultas\Pages;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Consultas\Actions\AgendarCitaAction;
use App\Filament\Admin\Resources\Consultas\Actions\CerrarConsultaAction;
use App\Filament\Admin\Resources\Consultas\Actions\ReactivarConsultaAction;
use App\Filament\Admin\Resources\Consultas\ConsultaResource;
use App\Filament\Admin\Resources\Consultas\Schemas\ConsultaForm;
use App\Models\Consulta;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditConsulta extends EditRecord
{
    protected static string $resource = ConsultaResource::class;

    public bool $editando = false;

    /**
     * Al venir de "Crear Consulta" (ver CreateConsulta::getRedirectUrl()),
     * abre de una vez la ventana de "Agendar" de esta misma ficha.
     *
     * mountAction() no sirve llamado directo acá: en la carga inicial de la
     * página los header actions todavía no están cacheados, así que no
     * encuentra la acción y no pasa nada. $this->js() en cambio deja la
     * llamada para el navegador, que la ejecuta ya con Livewire/Alpine
     * listos — el mismo camino que sigue un clic real en "Agendar".
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->editando = request()->boolean('editar') && auth()->user()?->puede('editar');

        if (request()->boolean('agendar')) {
            $this->js('$wire.mountAction(\'agendarCita\')');
        }
    }

    public function getTitle(): string
    {
        return 'N° '.$this->getRecord()->getKey();
    }

    /** Sin el último escalón "Editar": la migaja de pan queda "Consultas > N° X". */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = $this->getResourceBreadcrumbs();
        $breadcrumbs[] = $this->getRecordTitle();

        return $breadcrumbs;
    }

    /** Si se eligió "Agendar" al editar, ver mutateFormDataBeforeSave(). */
    protected bool $quiereAgendar = false;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = ConsultaForm::resolverOrigen($data);

        // "Agendar" no se guarda directo: el estado Agendado solo lo deja
        // ConfirmarAgendarCitaAction, cuando la cita ya quedó realmente
        // creada. Si se eligió esta opción pero todavía no hay una cita
        // vigente, se deja el estado que ya tenía (se abre el modal de
        // Agendar en save(), ver abajo) — así, si el modal se cierra sin
        // guardar ninguna cita, la consulta simplemente queda como estaba,
        // sin quedar "Agendado" a medias.
        if ($data['estado'] === EstadoConsulta::Agendado->value
            && ! $this->getRecord()->citas()->where('anulada', false)->exists()) {
            $this->quiereAgendar = true;
            $data['estado'] = $this->getRecord()->estado->value;
        }

        return $data;
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        abort_unless(auth()->user()?->puede('editar'), 403);

        $this->quiereAgendar = false;

        parent::save($shouldRedirect, $shouldSendSavedNotification);

        $this->editando = false;

        if ($this->quiereAgendar) {
            $this->redirect($this->urlFicha().'?agendar=1');

            return;
        }

        // Recarga la ficha en vista normal (los botones del pie no se
        // actualizan solos, ver getFormActions()).
        $this->redirect($this->urlFicha());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editarConsulta')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => ! $this->editando && auth()->user()?->puede('editar'))
                ->action(fn () => $this->redirect($this->urlFicha(editar: true))),
            // Insignia informativa (no clicable): señala de un vistazo si
            // esta consulta ya se convirtió en cliente ejecutivo (caso_id).
            Action::make('esClienteEjecutivo')
                ->label('Cliente Ejecutivo')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->disabled()
                ->visible(fn (Consulta $record) => $record->caso_id !== null),
            AgendarCitaAction::make(),
            CerrarConsultaAction::make(),
            ReactivarConsultaAction::make(),
        ];
    }

    /**
     * En la vista normal todos los campos están deshabilitados (ver
     * ConsultaForm), así que solo se ofrece, si la consulta ya está cerrada,
     * descargar el informe de cierre en PDF. En modo edición (ver
     * urlFicha()) se ofrecen "Guardar" y "Cancelar".
     *
     * Filament arma estos botones antes de ejecutar la acción del clic, así
     * que cambiar $this->editando en un clic no los actualiza: por eso
     * "Editar", "Cancelar" y "Guardar" recargan la ficha.
     */
    protected function getFormActions(): array
    {
        if ($this->editando) {
            return [
                $this->getSaveFormAction()->label('Guardar'),
                Action::make('cancelarEdicion')
                    ->label('Cancelar')
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
        $url = $this->getResourceUrl('edit', ['record' => $this->getRecord()]);

        return $editar ? $url.'?editar=1' : $url;
    }
}
