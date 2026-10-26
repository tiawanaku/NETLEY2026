<?php

namespace App\Filament\Admin\Resources\Llamadas\Actions;

use App\Models\Consulta;
use App\Models\Llamada;
use App\Rules\ContactoUnico;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Validator;

class CrearConsultaAction
{
    public static function make(): Action
    {
        return Action::make('crearConsulta')
            ->label('Crear consulta')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('success')
            ->visible(fn (Llamada $record) => $record->consulta_id === null)
            ->schema([
                Textarea::make('consulta')->label('Detalle de la consulta')->required(),
            ])
            ->action(function (Llamada $record, array $data, Action $action): void {
                // Igual que en "Crear consulta": no se crea si el teléfono o
                // el WhatsApp de la llamada ya están en OTRA consulta.
                $validacion = Validator::make($record->only(['telefono', 'whatsapp']), [
                    'telefono' => [new ContactoUnico(Consulta::class, ContactoUnico::TELEFONO, entidad: 'otra consulta')],
                    'whatsapp' => [new ContactoUnico(Consulta::class, ContactoUnico::TELEFONO, entidad: 'otra consulta')],
                ]);

                if ($validacion->fails()) {
                    Notification::make()
                        ->title('No se puede crear la consulta')
                        ->body(implode(' ', array_unique($validacion->errors()->all())))
                        ->danger()
                        ->persistent()
                        ->send();

                    $action->halt();
                }

                $consulta = Consulta::create([
                    'nombres' => $record->nombres,
                    'ap_paterno' => $record->ap_paterno,
                    'ap_materno' => $record->ap_materno,
                    'telefono' => $record->telefono,
                    'whatsapp' => $record->whatsapp,
                    'consulta' => $data['consulta'],
                    'fecha_consulta' => now(),
                    'ciudad' => $record->ciudad,
                    'origen' => $record->origen ?: 'llamada',
                    'origen_detalle' => $record->origen_detalle,
                    'estado' => 'pendiente',
                ]);

                $record->update(['consulta_id' => $consulta->id]);

                Notification::make()->title('Consulta creada desde la llamada')->success()->send();
            });
    }
}
