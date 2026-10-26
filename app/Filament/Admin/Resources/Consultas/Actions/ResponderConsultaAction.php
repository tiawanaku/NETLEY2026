<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Support\RespuestaFields;
use App\Models\Consulta;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Botón rápido en el listado para responder una consulta nueva (pendiente)
 * sin entrar a la ficha completa: registra la respuesta y pasa la consulta
 * a estado "Respondido".
 */
class ResponderConsultaAction
{
    public const OTRO = RespuestaFields::OTRO;

    public static function make(): Action
    {
        return Action::make('responderConsulta')
            ->label('Responder')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('primary')
            ->visible(fn (Consulta $record) => $record->estado === EstadoConsulta::Pendiente)
            ->schema([
                // La consulta original va primero, solo de lectura: para
                // responder hay que ver primero qué se preguntó.
                Textarea::make('consulta_original')
                    ->label('Consulta')
                    ->default(fn (Consulta $record) => $record->consulta)
                    ->disabled()
                    ->dehydrated(false)
                    ->rows(3)
                    ->columnSpanFull(),
                ...RespuestaFields::schema(),
            ])
            ->modalFooterActions(fn (Action $action): array => array_filter([
                $action->getModalSubmitAction(),
                $action->getModalCancelAction(),
            ]))
            ->modalSubmitActionLabel('Guardar')
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true']))
            ->action(function (Consulta $record, array $data): void {
                // "Respondido por" es siempre el usuario que está registrando la
                // respuesta, no un select manual.
                $record->respuestas()->create([
                    ...RespuestaFields::datosParaGuardar($data),
                    'personal_id' => auth()->user()?->personal_id,
                    'fecha_respuesta' => now(),
                    'paso' => 'respondido',
                ]);

                $record->update(['estado' => EstadoConsulta::Respondido]);

                Notification::make()->title('Respuesta registrada')->success()->send();
            });
    }
}
