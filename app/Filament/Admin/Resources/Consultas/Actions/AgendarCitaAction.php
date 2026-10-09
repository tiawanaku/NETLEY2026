<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Filament\Admin\Resources\Consultas\Actions\AnularCitaAction;
use App\Filament\Admin\Resources\Consultas\Actions\ReagendarCitaAction;
use App\Filament\Admin\Support\AgendaPreview;
use App\Models\Consulta;
use App\Support\PersonalEspecialidades;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Botón rápido para agendar una cita desde la ficha de la consulta, sin
 * pasar por la pestaña "Citas agendadas" (que quedó de solo lectura).
 */
class AgendarCitaAction
{
    public static function make(): Action
    {
        return Action::make('agendarCita')
            ->label('Agendar')
            ->icon('heroicon-o-calendar-days')
            ->color('info')
            ->schema([
                Select::make('personal')
                    ->label('Abogado(s) asignado(s)')
                    ->options(function (Consulta $record) {
                        // Si la consulta ya tiene una respuesta con materia legal
                        // definida, solo se listan los abogados con esa
                        // especialidad; si no tiene respuesta (o la respuesta usó
                        // "Otro" como materia), se listan todos los abogados, sin
                        // filtrar por especialidad — pero nunca otras profesiones.
                        $designacion = $record->respuestas()
                            ->latest('fecha_respuesta')
                            ->first()
                            ?->designacion;

                        return PersonalEspecialidades::personalOptionsWithEspecialidad(
                            $designacion?->value,
                            'Abogado',
                        );
                    })
                    ->allowHtml()
                    ->multiple()
                    ->searchable()
                    ->live()
                    ->extraAttributes(['data-enter-nav-field' => 'true']),
                Placeholder::make('agenda_personal')
                    ->label('Agenda (próximos 14 días)')
                    ->content(fn (Get $get) => AgendaPreview::render($get('personal')))
                    ->columnSpanFull(),
                DatePicker::make('fecha')->required()->default(now())->extraInputAttributes(['data-enter-nav' => 'true']),
                TimePicker::make('hora')->seconds(false)->required()->extraInputAttributes(['data-enter-nav' => 'true']),
                Textarea::make('detalle')->label('Nota')->rows(3)->extraInputAttributes(['data-enter-nav' => 'true']),
                Placeholder::make('citas_agendadas')
                    ->label('Citas agendadas')
                    ->content(fn (Consulta $record) => view('filament.admin.consultas.citas-agendadas', [
                        'citas' => $record->citas()->with('personal')->orderByDesc('fecha')->get(),
                        'puedeEditar' => auth()->user()?->puede('editar') ?? false,
                    ]))
                    ->columnSpanFull(),
            ])
            // registerModalActions() (no modalActions(), que está obsoleto y en
            // realidad REEMPLAZA todo el pie del modal, incluido el botón
            // Guardar) — solo registra "anularCita"/"reagendarCita" para que
            // los botones de citas-agendadas.blade.php puedan invocarlos vía
            // mountAction(), sin tocar el pie de este modal.
            ->registerModalActions([
                AnularCitaAction::make(),
                ReagendarCitaAction::make(),
            ])
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Guardar')
            ->modalCancelAction(false)
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true']))
            ->action(function (Consulta $record, array $data): void {
                $cita = $record->citas()->create([
                    'fecha' => $data['fecha'],
                    'hora' => $data['hora'],
                    'detalle' => $data['detalle'] ?? null,
                ]);

                if (filled($data['personal'] ?? null)) {
                    $cita->personal()->sync($data['personal']);
                }

                Notification::make()->title('Cita agendada')->success()->send();
            });
    }
}
