<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;

class ReactivarConsultaAction
{
    public static function make(): Action
    {
        return Action::make('reactivar')
            ->label('Reactivar')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (Consulta $record) => ! in_array($record->estado, EstadoConsulta::abiertos(), true) && $record->caso_id === null)
            ->requiresConfirmation()
            // Por defecto la confirmación pone "Cancelar" primero: acá queda
            // "Confirmar" primero y "Cancelar" después, centrados (mismo
            // arreglo que Retirar personal, ver .netley-eliminar-personal).
            ->modalSubmitActionLabel('Confirmar')
            ->modalCancelActionLabel('Cancelar')
            ->modalFooterActionsAlignment(Alignment::Start)
            ->extraModalWindowAttributes(['class' => 'netley-eliminar-personal'])
            ->action(function (Consulta $record): void {
                // Vuelve como Pendiente (su cita se anuló al cerrarse, ver
                // Consulta::booted()), lista para agendar de nuevo.
                $record->update(['estado' => EstadoConsulta::Pendiente]);
                Notification::make()->title('Consulta reactivada')->success()->send();
            });
    }
}
