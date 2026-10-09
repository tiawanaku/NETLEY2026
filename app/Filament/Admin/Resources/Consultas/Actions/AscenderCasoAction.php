<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Enums\EstadoConsulta;
use App\Enums\Rol;
use App\Filament\Admin\Support\CamposDomicilio;
use App\Filament\Admin\Support\ClienteCasoWizard;
use App\Models\Consulta;
use App\Models\Personal;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Wizard;

/**
 * Reemplaza ascender_caso.php: convierte una consulta (lead) en cliente + caso,
 * con pago inicial y plan de cuotas calculado en servidor (en el sistema legacy
 * el plan de cuotas se generaba en JavaScript del navegador). Los pasos del
 * wizard y la lógica de creación viven en ClienteCasoWizard, compartida con
 * CreateCliente (dar de alta un cliente directamente con el mismo flujo).
 */
class AscenderCasoAction
{
    public static function make(): Action
    {
        return Action::make('ascenderCaso')
            ->label('Cliente Ejecutivo')
            ->icon('heroicon-o-arrow-trending-up')
            ->color('success')
            ->visible(fn (Consulta $record) => $record->caso_id === null
                && in_array($record->estado, EstadoConsulta::abiertos(), true)
                && ! (auth()->user()?->hasRole(Rol::Pasante, Rol::Procurador) ?? false))
            ->fillForm(function (Consulta $record): array {
                // Precarga materia legal, delito y abogado(s) desde la última
                // respuesta registrada y desde el personal que ya atendió
                // citas agendadas de esta consulta, para no volver a
                // capturarlos manualmente en el paso "Proceso".
                $ultimaRespuesta = $record->respuestas()->latest('fecha_respuesta')->first();

                $personalIds = collect();

                if ($ultimaRespuesta?->personal_id) {
                    $personalIds->push($ultimaRespuesta->personal_id);
                }

                $personalIds = $personalIds->merge(
                    Personal::query()
                        ->whereHas('citas', fn ($query) => $query->where('consulta_id', $record->id))
                        ->pluck('id')
                );

                // El campo "Abogado(s) asignado(s)" solo admite personal con
                // profesión "Abogado"; se descarta cualquier otro id (ej. quien
                // respondió la consulta sin ser abogado) para no preseleccionar
                // a alguien que luego no aparece como opción válida.
                $personalIds = Personal::query()
                    ->whereIn('id', $personalIds->unique())
                    ->where('profesion', 'Abogado')
                    ->pluck('id');

                return [
                    'nombres' => $record->nombres,
                    'ap_paterno' => $record->ap_paterno,
                    'ap_materno' => $record->ap_materno,
                    'telefono' => $record->telefono,
                    'whatsapp' => $record->whatsapp,
                    'correo' => $record->correo,
                    // País, provincia, ciudad, dirección, zona, calles, N°,
                    // indicaciones y punto del mapa, tal como se capturaron
                    // en la consulta.
                    ...$record->only(CamposDomicilio::COLUMNAS),
                    'fecha_inicio' => now()->toDateString(),
                    'fecha_primera_cuota' => now()->addMonth()->toDateString(),
                    'especialidad' => $ultimaRespuesta?->designacion?->value,
                    'delito_id' => $ultimaRespuesta?->delito_id,
                    'personal' => $personalIds->unique()->values()->all(),
                ];
            })
            ->schema([
                Wizard::make(ClienteCasoWizard::steps())
                    // Marca compartida con el botón real de guardar (más abajo):
                    // el de navegación con Enter hace clic en el que esté
                    // visible — "Siguiente" en los primeros pasos (el de
                    // Guardar queda oculto por el wizard hasta no ser así) y,
                    // en el último paso, al estar "Siguiente" oculto, cae en
                    // el de Guardar.
                    ->nextAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true'])),
            ])
            ->modalSubmitActionLabel('Guardar')
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true']))
            ->action(function (Consulta $record, array $data): void {
                $resultado = ClienteCasoWizard::crear($data);

                $record->update([
                    'caso_id' => $resultado['caso']->id,
                    'estado' => EstadoConsulta::CasoIniciado,
                ]);

                Notification::make()->title('Consulta ascendida a caso')->success()->send();
            });
    }
}
