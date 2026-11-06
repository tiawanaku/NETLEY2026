<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Models\Consulta;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

/** "Primera reprogramación" / "Segunda reprogramación" del formulario de papel: no hay límite, se puede reagendar las veces que haga falta, cada una queda anotada en observaciones. */
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
                TextInput::make('bs_iguala')->label('Bs. Iguala')->numeric()->prefix('Bs.')->extraInputAttributes(['data-enter-nav' => 'true']),
                TextInput::make('bs_anticipo')->label('Bs. Anticipo')->numeric()->prefix('Bs.')->extraInputAttributes(['data-enter-nav' => 'true']),
                TextInput::make('porcentaje_comision')->label('% Comisión')->numeric()->prefix('%')->extraInputAttributes(['data-enter-nav' => 'true']),
                TextInput::make('bs_comision')->label('Bs. Comisión')->numeric()->prefix('Bs.')->extraInputAttributes(['data-enter-nav' => 'true']),
                Toggle::make('confirmada')->label('Cita confirmada'),
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
                    'bs_iguala' => $cita->bs_iguala,
                    'bs_anticipo' => $cita->bs_anticipo,
                    'porcentaje_comision' => $cita->porcentaje_comision,
                    'bs_comision' => $cita->bs_comision,
                    'confirmada' => $cita->confirmada,
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
                    'bs_iguala' => $data['bs_iguala'] ?? null,
                    'bs_anticipo' => $data['bs_anticipo'] ?? null,
                    'porcentaje_comision' => $data['porcentaje_comision'] ?? null,
                    'bs_comision' => $data['bs_comision'] ?? null,
                    'confirmada' => $data['confirmada'] ?? false,
                    'observaciones' => $cita->observacionesCon('Reagendada', $data['observacion']),
                ]);

                Notification::make()->title('Cita reagendada')->success()->send();
            });
    }
}
