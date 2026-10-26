<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

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
            ->action(function (Consulta $record): void {
                $record->update(['estado' => EstadoConsulta::Reactivado]);
                Notification::make()->title('Consulta reactivada')->success()->send();
            });
    }
}
