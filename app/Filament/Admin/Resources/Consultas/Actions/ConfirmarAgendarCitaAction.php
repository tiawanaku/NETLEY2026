<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Consultas\ConsultaResource;
use App\Models\Consulta;
use App\Support\FormularioCita;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\DB;

/**
 * Último paso de AgendarCitaAction: al presionar Enter/Guardar en el
 * formulario de Agendar, en vez de guardar directo se abre esta ventana de
 * confirmación (Sí/No, con Enter también confirma — ver data-enter-confirm
 * en enter-navigation.js). Los datos ya validados del formulario llegan en
 * $arguments['data'] (ver AgendarCitaAction::make()), que es quien monta
 * esta acción.
 */
class ConfirmarAgendarCitaAction
{
    public static function make(): Action
    {
        return Action::make('confirmarAgendarCita')
            ->requiresConfirmation()
            ->modalHeading('¿Agendar esta cita?')
            ->modalDescription('Revisa los datos antes de confirmar. Puedes presionar Enter para confirmar.')
            ->modalSubmitActionLabel('Sí')
            ->modalCancelActionLabel('No')
            // Por defecto, al pedir confirmación Filament centra los botones
            // invirtiendo su orden (Cancelar antes que el de enviar) — acá se
            // fija el orden normal para que "Sí" quede a la izquierda y "No"
            // a la derecha, y el centrado/ancho se hace aparte por CSS (ver
            // .netley-confirmar-agendar en admin-theme.css) para no perder
            // ese orden.
            ->modalFooterActionsAlignment(Alignment::Start)
            ->extraModalWindowAttributes(['data-enter-confirm' => 'true', 'class' => 'netley-confirmar-agendar'])
            ->action(function (Consulta $record, array $arguments, $livewire): void {
                $data = $arguments['data'];

                // Reagendar (ver VerAgendado::guardarCierre()): la cita nueva
                // conserva los documentos de la anterior (formularios y lo
                // que se haya subido), y suma los que se adjunten ahora.
                $anterior = filled($arguments['copiarDeCitaId'] ?? null)
                    ? $record->citas()->find($arguments['copiarDeCitaId'])
                    : null;

                DB::transaction(function () use ($record, $data, $anterior): void {
                    $record->update([
                        'fecha_registro' => $data['fecha_registro'],
                        'atendido_por_id' => $data['atendido_por_id'],
                    ]);

                    $cita = $record->citas()->create([
                        'fecha' => $data['fecha'],
                        'hora' => $data['hora'],
                        'detalle' => $data['detalle'] ?? null,
                        'bs_iguala' => $data['bs_iguala'] ?? null,
                        'bs_anticipo' => $data['bs_anticipo'] ?? null,
                        'porcentaje_comision' => $data['porcentaje_comision'] ?? null,
                        'bs_comision' => $data['bs_comision'] ?? null,
                        'confirmada' => $data['confirmada'] ?? false,
                        'documentos' => [
                            ...($anterior?->documentos ?? []),
                            ...array_values($data['documentos'] ?? []),
                        ],
                    ]);

                    if (filled($data['personal'] ?? null)) {
                        $cita->personal()->sync($data['personal']);
                    }

                    // Al agendar una cita, la consulta queda "Agendado" sin
                    // importar en qué estado estuviera antes.
                    $record->update(['estado' => EstadoConsulta::Agendado]);

                    // Cada cita nueva genera su "Formulario N" ya llenado,
                    // primero en la lista de documentos.
                    FormularioCita::generar($cita->fresh());
                });

                Notification::make()->title('Cita agendada')->success()->send();

                // Una vez registrada la cita, vuelve siempre a "Nuevas
                // consultas" (sea cual sea la pantalla desde la que se
                // agendó: EditConsulta o VerAgendado vía motivo "Reagendar")
                // para seguir con la siguiente consulta pendiente.
                $livewire->redirect(ConsultaResource::getUrl('index').'?tab=nuevas');
            });
    }
}
