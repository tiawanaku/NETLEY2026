<?php

namespace App\Filament\Admin\Resources\Consultas\Actions;

use App\Enums\EstadoPersonal;
use App\Filament\Admin\Resources\Consultas\Actions\AnularCitaAction;
use App\Filament\Admin\Resources\Consultas\Actions\ConfirmarAgendarCitaAction;
use App\Filament\Admin\Resources\Consultas\Actions\ReagendarCitaAction;
use App\Filament\Admin\Support\AdjuntarDocumentosClip;
use App\Filament\Admin\Support\AgendaPreview;
use App\Filament\Admin\Support\CamposServicios;
use App\Filament\Admin\Support\CampoHora;
use App\Models\Consulta;
use App\Models\Personal;
use App\Support\PersonalEspecialidades;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                DatePicker::make('fecha_registro')
                    ->label('Fecha de registro')
                    ->required()
                    ->extraInputAttributes(['data-enter-nav' => 'true']),
                Select::make('atendido_por_id')
                    ->label('Atendido por')
                    // Personal habilitado, más el que ya figura en la consulta
                    // aunque hoy esté deshabilitado (si no, reagendar falla
                    // con "el valor seleccionado no es válido").
                    ->options(fn ($state) => Personal::query()
                        ->where(fn ($q) => $q->where('estado', EstadoPersonal::Habilitado)
                            ->when(filled($state), fn ($q) => $q->orWhere('id', $state)))
                        ->orderBy('nombres')
                        ->get()
                        ->mapWithKeys(fn (Personal $personal) => [$personal->id => $personal->nombre_completo]))
                    ->native(false)
                    ->searchable()
                    ->required()
                    ->extraAttributes(['data-enter-nav-field' => 'true']),
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
                    // El select de "multiple" por defecto queda abierto tras
                    // cada elección (para poder marcar varios seguidos); acá
                    // se prefiere que se colapse apenas se elige uno. No hay
                    // una opción nativa para eso, así que se simula un clic
                    // fuera del select (en el <body>), que es justamente lo
                    // que ya hace que se cierre solo.
                    ->afterStateUpdated(fn ($livewire) => $livewire->js('document.body.click()'))
                    ->extraAttributes(['data-enter-nav-field' => 'true']),
                // Una vez elegidos fecha y hora, ya cumplió su función (evitar
                // sobreponer horarios) — se oculta sola para dejar más
                // espacio al resto del formulario.
                Placeholder::make('agenda_personal')
                    ->label('Agenda (próximos 14 días)')
                    ->content(fn (Get $get) => AgendaPreview::render($get('personal')))
                    ->visible(fn (Get $get): bool => blank($get('fecha')) || ! CampoHora::esValida($get('hora')))
                    ->columnSpanFull(),
                // El id fijo lo usa agenda-preview.blade.php para escribir la
                // fecha directo al hacer clic en un día del mini-calendario.
                // ->live() (no onBlur): ese clic dispara un evento "change"
                // sintético, no un blur real, y agenda_personal de arriba
                // necesita enterarse al toque para poder ocultarse.
                DatePicker::make('fecha')->required()->live()->extraInputAttributes(['data-enter-nav' => 'true', 'id' => 'agendar-cita-fecha']),
                // onBlur: re-renderizar a cada tecla cortaría lo que se está
                // escribiendo; basta con avisar al salir del campo.
                CampoHora::make('hora')->label('Hora')->required()->live(onBlur: true),
                CamposServicios::make(),
                Textarea::make('detalle')->label('Nota')->rows(3)->extraInputAttributes(['data-enter-nav' => 'true']),
                // Datos de comisión del formulario de papel "Consultas directas".
                TextInput::make('bs_iguala')->label('Bs. Iguala')->numeric()->prefix('Bs.')->extraInputAttributes(['data-enter-nav' => 'true']),
                TextInput::make('bs_anticipo')->label('Bs. Anticipo')->numeric()->prefix('Bs.')->extraInputAttributes(['data-enter-nav' => 'true']),
                TextInput::make('porcentaje_comision')->label('% Comisión')->numeric()->prefix('%')->extraInputAttributes(['data-enter-nav' => 'true']),
                TextInput::make('bs_comision')->label('Bs. Comisión')->numeric()->prefix('Bs.')->extraInputAttributes(['data-enter-nav' => 'true']),
                Repeater::make('documentos')
                    ->label('Adjuntar documentos')
                    ->extraAttributes(['class' => 'netley-fila-documento'])
                    ->schema([
                        // El archivo ya llega adjunto desde el clip (ver
                        // AdjuntarDocumentosClip): este campo solo sigue
                        // existiendo para que Filament lo guarde al guardar
                        // el formulario, pero no se muestra (ver
                        // .netley-documento-oculto) — el nombre del archivo
                        // se identifica con el Placeholder de al lado.
                        FileUpload::make('ruta')
                            ->hiddenLabel()
                            ->directory('documentos-citas')
                            ->extraAttributes(['class' => 'netley-documento-oculto'])
                            ->required(),
                        Placeholder::make('nombreArchivo')
                            ->label('Archivo')
                            ->content(fn (Get $get) => AdjuntarDocumentosClip::nombreArchivo($get('ruta')) ?? '—'),
                        TextInput::make('descripcion')
                            ->label('Descripción')
                            ->maxLength(100),
                    ])
                    ->columns(2)
                    // Sin botón de agregar propio: lo reemplaza el campo
                    // "clip" de abajo (ver AdjuntarDocumentosClip), que sí
                    // puede abrir el explorador en un solo clic porque ya
                    // existe en el formulario desde el principio.
                    ->addable(false)
                    ->reorderable(false)
                    ->defaultItems(0)
                    ->columnSpanFull(),
                AdjuntarDocumentosClip::make('documentos', 'documentos-citas'),
            ])
            // registerModalActions() (no modalActions(), que está obsoleto y en
            // realidad REEMPLAZA todo el pie del modal, incluido el botón
            // Guardar) — registra "anularCita"/"reagendarCita" para que los
            // botones de citas-agendadas.blade.php puedan invocarlos vía
            // mountAction(), y "confirmarAgendarCita" (ver más abajo) para el
            // paso de confirmación antes de guardar.
            ->registerModalActions([
                AnularCitaAction::make(),
                ReagendarCitaAction::make(),
                ConfirmarAgendarCitaAction::make(),
            ])
            // Valores iniciales de "Fecha de registro"/"Atendido por"/"Fecha"
            // (antes en ->default() de cada campo, ver más arriba). Si se
            // monta con 'copiarDeCitaId' (ver VerAgendado::guardarCierre(),
            // motivo "Reagendar": la cita anterior queda anulada y esta
            // misma acción abre para cargar una nueva), se copian también
            // abogado(s) y datos de comisión de esa cita — pero nunca su
            // fecha/hora, que vuelve a elegir la persona.
            ->fillForm(function (Consulta $record, array $arguments): array {
                $valores = [
                    'fecha_registro' => $record->fecha_registro ?? now(),
                    'atendido_por_id' => $record->atendido_por_id ?? auth()->user()?->personal_id,
                ];

                $anterior = filled($arguments['copiarDeCitaId'] ?? null)
                    ? $record->citas()->find($arguments['copiarDeCitaId'])
                    : null;

                if (! $anterior) {
                    $valores['fecha'] = now();

                    return [...$valores, ...CamposServicios::desde(null)];
                }

                return [
                    ...$valores,
                    ...CamposServicios::desde($anterior),
                    'personal' => $anterior->personal->pluck('id')->all(),
                    'bs_iguala' => $anterior->bs_iguala,
                    'bs_anticipo' => $anterior->bs_anticipo,
                    'porcentaje_comision' => $anterior->porcentaje_comision,
                    'bs_comision' => $anterior->bs_comision,
                ];
            })
            // Mismo formulario para agendar una cita nueva y para "Reagendar"
            // desde Cerrar consulta (ver fillForm() arriba): el título deja
            // en claro cuál de los dos es, para no parecer que reapareció el
            // botón "Agendar" que se sacó de esta ficha.
            ->modalHeading(fn (array $arguments): string => filled($arguments['copiarDeCitaId'] ?? null) ? 'Reagendar' : 'Agendar')
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Guardar')
            ->modalCancelActionLabel('Cancelar')
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-enter-submit' => 'true']))
            // No guarda directo: abre la confirmación de
            // ConfirmarAgendarCitaAction con los datos ya validados de este
            // formulario (Filament detecta que se montó otra acción dentro
            // de esta y no cierra el modal de los campos de golpe, lo
            // reemplaza por el de confirmación).
            ->action(function (array $data, array $arguments, $livewire): void {
                $livewire->mountAction('confirmarAgendarCita', [
                    'data' => $data,
                    'copiarDeCitaId' => $arguments['copiarDeCitaId'] ?? null,
                ]);
            });
    }
}
