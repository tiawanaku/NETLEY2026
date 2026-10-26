<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Models\Consulta;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class AnularCitaAction
{
    public static function make(): Action
    {
        return Action::make('anularCita')
            ->label('Anular')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->schema([
                Textarea::make('observacion')
                    ->label('Nota de justificación')
                    ->required()
                    ->rows(3)
                    ->extraInputAttributes(['data-enter-nav' => 'true']),
            ])
            ->modalSubmitActionLabel('Guardar')
            ->modalCancelAction(false)
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true']))
            ->action(function (Consulta $record, array $arguments, array $data): void {
                $cita = $record->citas()->findOrFail($arguments['cita']);

                $cita->update([
                    'anulada' => true,
                    'observaciones' => $cita->observacionesCon('Anulada', $data['observacion']),
                ]);

                Notification::make()->title('Cita anulada')->success()->send();
            });
    }
}
