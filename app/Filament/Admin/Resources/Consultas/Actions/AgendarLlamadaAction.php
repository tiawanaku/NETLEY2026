<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Models\Consulta;
use App\Support\PersonalEspecialidades;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;

/**
 * Botón rápido para agendar una llamada de seguimiento desde la ficha de la
 * consulta; copia los datos de contacto de la consulta para no repetirlos.
 */
class AgendarLlamadaAction
{
    public static function make(): Action
    {
        return Action::make('agendarLlamada')
            ->label('Agendar llamada')
            ->icon('heroicon-o-phone')
            ->color('warning')
            ->schema([
                Select::make('personal_id')
                    ->label('Personal asignado')
                    ->options(fn () => PersonalEspecialidades::personalOptionsWithEspecialidad())
                    ->allowHtml()
                    ->searchable()
                    ->extraAttributes(['data-enter-nav-field' => 'true']),
                DatePicker::make('fecha')->required()->default(now())->extraInputAttributes(['data-enter-nav' => 'true']),
                TimePicker::make('hora')->seconds(false)->required()->extraInputAttributes(['data-enter-nav' => 'true']),
                TextInput::make('accion')->label('Acción')->extraInputAttributes(['data-enter-nav' => 'true']),
            ])
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true']))
            ->action(function (Consulta $record, array $data): void {
                $record->llamadas()->create([
                    'personal_id' => $data['personal_id'] ?? null,
                    'nombres' => $record->nombres,
                    'ap_paterno' => $record->ap_paterno,
                    'ap_materno' => $record->ap_materno,
                    'telefono' => $record->telefono,
                    'whatsapp' => $record->whatsapp,
                    'ciudad' => $record->ciudad,
                    'numero_consulta' => (string) $record->id,
                    'origen' => $record->origen,
                    'fecha' => $data['fecha'],
                    'hora' => $data['hora'],
                    'accion' => $data['accion'] ?? null,
                ]);

                Notification::make()->title('Llamada agendada')->success()->send();
            });
    }
}
