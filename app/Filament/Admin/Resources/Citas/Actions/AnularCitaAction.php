<?php

namespace App\Filament\Admin\Resources\Citas\Actions;

use App\Models\Cita;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class AnularCitaAction
{
    public static function make(): Action
    {
        return Action::make('anular')
            ->label('Anular')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Cita $record) => ! $record->anulada)
            ->requiresConfirmation()
            ->action(function (Cita $record): void {
                $record->update(['anulada' => true]);
                Notification::make()->title('Cita anulada')->success()->send();
            });
    }
}
