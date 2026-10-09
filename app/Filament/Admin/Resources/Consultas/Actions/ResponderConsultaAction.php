<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Enums\Especialidad;
use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Delito;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Botón rápido en el listado para responder una consulta nueva (pendiente)
 * sin entrar a la ficha completa: registra la respuesta y pasa la consulta
 * a estado "Respondido".
 */
class ResponderConsultaAction
{
    public const OTRO = 'otro';

    public static function make(): Action
    {
        return Action::make('responderConsulta')
            ->label('Responder')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('primary')
            ->visible(fn (Consulta $record) => $record->estado === EstadoConsulta::Pendiente)
            ->schema([
                Select::make('designacion')
                    ->label('Materia legal')
                    ->options(fn () => collect(Especialidad::cases())
                        ->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()])
                        ->put(self::OTRO, 'Otro'))
                    ->native(false)
                    ->live()
                    ->required()
                    ->afterStateUpdated(fn ($set) => $set('delito_id', null))
                    ->extraAttributes(['data-enter-nav-field' => 'true', 'data-enter-nav-live' => 'true']),
                TextInput::make('materia_texto')
                    ->label('Materia legal (manual)')
                    ->maxLength(120)
                    ->required()
                    ->visible(fn (Get $get) => $get('designacion') === self::OTRO)
                    ->extraInputAttributes(['data-enter-nav' => 'true']),
                Select::make('delito_id')
                    ->label('Delito / Materia específica')
                    ->options(function (Get $get) {
                        $area = $get('designacion');

                        if (! $area) {
                            return [];
                        }

                        $opciones = $area === self::OTRO
                            ? []
                            : Delito::query()->where('area', $area)->orderBy('delito')->pluck('delito', 'id')->all();

                        return $opciones + [self::OTRO => 'Otro'];
                    })
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->disabled(fn (Get $get) => blank($get('designacion')))
                    ->helperText(fn (Get $get) => blank($get('designacion')) ? 'Selecciona primero una materia legal.' : null)
                    ->extraAttributes(['data-enter-nav-field' => 'true', 'data-enter-nav-live' => 'true']),
                TextInput::make('delito_texto')
                    ->label('Delito (manual)')
                    ->maxLength(255)
                    ->required()
                    ->visible(fn (Get $get) => $get('delito_id') === self::OTRO)
                    ->extraInputAttributes(['data-enter-nav' => 'true']),
                Textarea::make('respuesta')
                    ->required()
                    ->rows(3)
                    ->extraInputAttributes(['data-enter-nav' => 'true']),
            ])
            ->modalFooterActions(fn (Action $action): array => array_filter([
                $action->getModalCancelAction(),
                $action->getModalSubmitAction(),
            ]))
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true']))
            ->action(function (Consulta $record, array $data): void {
                $esOtraMateria = $data['designacion'] === self::OTRO;
                $esOtroDelito = ($data['delito_id'] ?? null) === self::OTRO;

                // "Respondido por" es siempre el usuario que está registrando la
                // respuesta, no un select manual.
                $record->respuestas()->create([
                    'respuesta' => $data['respuesta'],
                    'designacion' => $esOtraMateria ? null : $data['designacion'],
                    'materia_texto' => $esOtraMateria ? $data['materia_texto'] : null,
                    'delito_id' => $esOtroDelito ? null : ($data['delito_id'] ?? null),
                    'delito_texto' => $esOtroDelito ? $data['delito_texto'] : null,
                    'personal_id' => auth()->user()?->personal_id,
                    'fecha_respuesta' => now(),
                    'paso' => 'respondido',
                ]);

                $record->update(['estado' => EstadoConsulta::Respondido]);

                Notification::make()->title('Respuesta registrada')->success()->send();
            });
    }
}
