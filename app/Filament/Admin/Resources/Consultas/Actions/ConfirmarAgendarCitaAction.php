<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
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
            ->action(function (Consulta $record, array $arguments): void {
                $data = $arguments['data'];

                DB::transaction(function () use ($record, $data): void {
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
                        'documentos' => $data['documentos'] ?? null,
                    ]);

                    if (filled($data['personal'] ?? null)) {
                        $cita->personal()->sync($data['personal']);
                    }

                    // Al agendar una cita, la consulta queda "Agendado" sin
                    // importar en qué estado estuviera antes.
                    $record->update(['estado' => EstadoConsulta::Agendado]);
                });

                Notification::make()->title('Cita agendada')->success()->send();
            });
    }
}
