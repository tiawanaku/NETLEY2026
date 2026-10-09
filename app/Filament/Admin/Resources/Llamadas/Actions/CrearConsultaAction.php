<?php

namespace App\Filament\Admin\Resources\Llamadas\Actions;

use App\Models\Consulta;
use App\Models\Llamada;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

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
            ->action(function (Llamada $record, array $data): void {
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
                    'estado' => 'pendiente',
                ]);

                $record->update(['consulta_id' => $consulta->id]);

                Notification::make()->title('Consulta creada desde la llamada')->success()->send();
            });
    }
}
