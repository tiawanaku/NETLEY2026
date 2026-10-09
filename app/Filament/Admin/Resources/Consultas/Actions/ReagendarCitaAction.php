<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Models\Consulta;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;

class ReagendarCitaAction
{
    public static function make(): Action
    {
        return Action::make('reagendarCita')
            ->label('Reagendar')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->schema([
                DatePicker::make('fecha')->required()->extraInputAttributes(['data-enter-nav' => 'true']),
                TimePicker::make('hora')->seconds(false)->required()->extraInputAttributes(['data-enter-nav' => 'true']),
                Textarea::make('observacion')
                    ->label('Nota de justificación')
                    ->required()
                    ->rows(3)
                    ->extraInputAttributes(['data-enter-nav' => 'true']),
            ])
            ->fillForm(function (Consulta $record, array $arguments): array {
                $cita = $record->citas()->findOrFail($arguments['cita']);

                return [
                    'fecha' => $cita->fecha,
                    'hora' => substr((string) $cita->hora, 0, 5),
                ];
            })
            ->modalSubmitActionLabel('Guardar')
            ->modalCancelAction(false)
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true']))
            ->action(function (Consulta $record, array $arguments, array $data): void {
                $cita = $record->citas()->findOrFail($arguments['cita']);

                $cita->update([
                    'fecha' => $data['fecha'],
                    'hora' => $data['hora'],
                    'observaciones' => $cita->observacionesCon('Reagendada', $data['observacion']),
                ]);

                Notification::make()->title('Cita reagendada')->success()->send();
            });
    }
}
