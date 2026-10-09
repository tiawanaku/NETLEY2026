<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Cierra una consulta sin ascenderla a caso, dejando registrado el motivo
 * (solo consulta / no responde / no contesta / no requiere los servicios)
 * y un detalle libre, igual que el cierre de consultas del sistema legacy.
 */
class CerrarConsultaAction
{
    protected static function motivos(): array
    {
        return [
            EstadoConsulta::SoloConsulta->value => 'Solo consulta',
            EstadoConsulta::NoResponde->value => 'No responde',
            EstadoConsulta::NoContesta->value => 'No contesta',
            EstadoConsulta::NoRequiereServicios->value => 'No requiere los servicios',
        ];
    }

    public static function make(): Action
    {
        return Action::make('cerrarConsulta')
            ->label('Cerrar consulta')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Consulta $record) => in_array($record->estado, EstadoConsulta::abiertos(), true)
                && $record->caso_id === null)
            ->requiresConfirmation()
            ->schema([
                Select::make('motivo')
                    ->label('Motivo de cierre')
                    ->options(self::motivos())
                    ->required()
                    ->extraInputAttributes(['data-enter-nav' => 'true']),
                Textarea::make('detalles')
                    ->label('Detalles')
                    ->rows(4)
                    ->extraInputAttributes(['data-enter-nav' => 'true']),
            ])
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true']))
            ->action(function (Consulta $record, array $data): void {
                $nota = trim((string) $record->nota_interna);
                $detalle = trim((string) ($data['detalles'] ?? ''));

                if ($detalle !== '') {
                    $motivoLabel = self::motivos()[$data['motivo']] ?? $data['motivo'];
                    $entrada = now()->format('d/m/Y H:i')." — Cierre ({$motivoLabel}): {$detalle}";
                    $nota = $nota !== '' ? "{$nota}\n{$entrada}" : $entrada;
                }

                $record->update([
                    'estado' => $data['motivo'],
                    'nota_interna' => $nota,
                ]);

                Notification::make()->title('Consulta cerrada')->success()->send();
            });
    }
}
