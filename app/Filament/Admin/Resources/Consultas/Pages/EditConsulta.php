<?php

namespace App\Filament\Admin\Resources\Consultas\Pages;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Consultas\Actions\AgendarCitaAction;
use App\Filament\Admin\Resources\Consultas\Actions\AgendarLlamadaAction;
use App\Filament\Admin\Resources\Consultas\Actions\AscenderCasoAction;
use App\Filament\Admin\Resources\Consultas\Actions\CerrarConsultaAction;
use App\Filament\Admin\Resources\Consultas\Actions\ReactivarConsultaAction;
use App\Filament\Admin\Resources\Consultas\Actions\ResponderConsultaAction;
use App\Filament\Admin\Resources\Consultas\ConsultaResource;
use App\Models\Consulta;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditConsulta extends EditRecord
{
    protected static string $resource = ConsultaResource::class;

    public bool $editando = false;

    public function getTitle(): string
    {
        return 'N° '.$this->getRecord()->getKey();
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        abort_unless(auth()->user()?->puede('editar'), 403);

        parent::save($shouldRedirect, $shouldSendSavedNotification);

        $this->editando = false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editarConsulta')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => ! $this->editando && auth()->user()?->puede('editar'))
                ->action(fn () => $this->editando = true),
            // Insignia informativa (no clicable): señala de un vistazo si
            // esta consulta ya se convirtió en cliente ejecutivo (caso_id).
            Action::make('esClienteEjecutivo')
                ->label('Cliente Ejecutivo')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->disabled()
                ->visible(fn (Consulta $record) => $record->caso_id !== null),
            ResponderConsultaAction::make(),
            AgendarCitaAction::make(),
            AgendarLlamadaAction::make(),
            AscenderCasoAction::make(),
            CerrarConsultaAction::make(),
            ReactivarConsultaAction::make(),
        ];
    }

    /**
     * Todos los campos del formulario quedan deshabilitados en esta vista
     * (ver ConsultaForm), así que no hay nada que "Guardar" — se deja el
     * botón para volver al listado y, si la consulta ya está cerrada, el
     * de descargar el informe de cierre en PDF.
     */
    protected function getFormActions(): array
    {
        if ($this->editando) {
            return [
                $this->getSaveFormAction()->label('Guardar cambios'),
                Action::make('cancelarEdicion')
                    ->label('Cancelar edición')
                    ->color('gray')
                    ->action(function () {
                        $this->editando = false;
                        $this->fillForm();
                    }),
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
}
