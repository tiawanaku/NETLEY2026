<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Models\Consulta;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/** Marca una cita como confirmada (casilla "Cita confirmada" del formulario de papel), sin pedir datos. */
class ConfirmarCitaAction
{
    public static function make(): Action
    {
        return Action::make('confirmarCita')
            ->label('Confirmar')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (Consulta $record, array $arguments): void {
                $cita = $record->citas()->findOrFail($arguments['cita']);

                $cita->update(['confirmada' => true]);

                Notification::make()->title('Cita confirmada')->success()->send();
            });
    }
}
