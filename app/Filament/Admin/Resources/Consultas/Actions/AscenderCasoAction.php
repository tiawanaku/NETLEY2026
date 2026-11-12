<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Enums\EstadoConsulta;
use App\Enums\Rol;
use App\Filament\Admin\Forms\Components\DelitoSelect;
use App\Filament\Admin\Resources\Clientes\ClienteResource;
use App\Filament\Admin\Support\CamposDomicilio;
use App\Filament\Admin\Support\CamposServicios;
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

                // Cita vigente de la consulta: sus "Servicios requeridos" y su
                // Nota pasan al paso "Proceso" (interruptores y Descripción).
                $cita = $record->citas()->where('anulada', false)->orderByDesc('fecha')->orderByDesc('hora')->first();

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
                    'patrocinio_hih' => false,
                    'anticipo' => 0,
                    // Si la respuesta usó materia "Otro" (escrita a mano), se
                    // precarga como "Otros" con ese mismo texto.
                    'especialidad' => $ultimaRespuesta?->designacion?->value
                        ?? ($ultimaRespuesta?->materia_texto ? DelitoSelect::OTROS : null),
                    'materia_texto' => $ultimaRespuesta?->designacion ? null : $ultimaRespuesta?->materia_texto,
                    // Sin delito del catálogo pero con delito o materia
                    // escritos, "Otro" (ver DelitoSelect::make()).
                    'delito_id' => $ultimaRespuesta?->delito_id
                        ?? ($ultimaRespuesta?->delito_texto || (! $ultimaRespuesta?->designacion && $ultimaRespuesta?->materia_texto)
                            ? DelitoSelect::OTROS
                            : null),
                    'delito_texto' => $ultimaRespuesta?->delito_id ? null : $ultimaRespuesta?->delito_texto,
                    'personal' => $personalIds->unique()->values()->all(),
                    ...CamposServicios::desde($cita),
                    'descripcion' => $cita?->detalle,
                ];
            })
            // ->steps() (asistente propio de la acción, no un Wizard dentro
            // del formulario): así el botón Guardar va dentro del asistente y
            // solo aparece en el último paso (4, "Adjuntar documentos"), y no
            // hay botón Cancelar (se cierra con la X del encabezado).
            ->steps(ClienteCasoWizard::steps())
            // Marca compartida con el botón de guardar (más abajo): la
            // navegación con Enter hace clic en el que esté visible —
            // "Siguiente" en los pasos 1 a 3 y Guardar en el 4.
            ->modifyWizardUsing(fn (Wizard $wizard): Wizard => $wizard
                ->nextAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true'])))
            ->modalCancelAction(false)
            ->modalSubmitActionLabel('Guardar')
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true']))
            ->action(function (Consulta $record, array $data, $livewire): void {
                $resultado = ClienteCasoWizard::crear($data);

                $record->update([
                    'caso_id' => $resultado['caso']->id,
                    'estado' => EstadoConsulta::CasoIniciado,
                ]);

                Notification::make()->title('Consulta ascendida a caso')->success()->send();

                // Lleva al listado de Clientes ejecutivos, donde queda ahora.
                $livewire->redirect(ClienteResource::getUrl('index'));
            });
    }
}
