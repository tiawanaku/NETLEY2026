<?php

namespace App\Filament\Admin\Resources\Personals\Pages;

use App\Filament\Admin\Resources\Personals\Actions\PersonalActions;
use App\Filament\Admin\Resources\Personals\PersonalResource;
use App\Models\DocumentoPersonal;
use App\Models\Personal;
use App\Support\PersonalEspecialidades;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class EditPersonal extends EditRecord
{
    protected static string $resource = PersonalResource::class;

    public bool $editando = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Un retirado solo se ve; no se edita (primero hay que habilitarlo).
        $this->editando = request()->boolean('editar') && auth()->user()?->puede('editar') && ! $this->getRecord()->trashed();
    }

    public function getTitle(): string
    {
        return 'ITEM '.$this->record->id;
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        abort_unless(auth()->user()?->puede('editar'), 403);

        parent::save($shouldRedirect, $shouldSendSavedNotification);

        $this->editando = false;

        // Recarga la ficha en vista normal (los botones del pie no se
        // actualizan solos, ver getFormActions()).
        $this->redirect($this->urlFicha());
    }

    /** Documento de ESTE personal (nunca de otro, aunque se cambie el id). */
    protected function documentoPersonal(array $arguments): DocumentoPersonal
    {
        return $this->getRecord()->documentos()->findOrFail($arguments['documento'] ?? 0);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editarPersonal')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => ! $this->editando && auth()->user()?->puede('editar') && ! $this->getRecord()->trashed())
                ->action(fn () => $this->redirect($this->urlFicha(editar: true))),
            // Tabla Fecha / Descripción / Documento con Descargar, Editar
            // (solo la descripción) y Eliminar (documentos-personal.blade.php).
            // Esas 3 se invocan desde dentro del modal: sin registrarlas acá
            // como hijas, Filament no las encuentra en ese contexto anidado.
            Action::make('verDocumentos')
                ->label('Ver documentos')
                ->icon('heroicon-o-folder-open')
                ->color('primary')
                ->modalHeading('Documentos adjuntos')
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->registerModalActions([
                    Action::make('descargarDocumentoPersonal')
                        ->action(function (array $arguments) {
                            $documento = $this->documentoPersonal($arguments);
                            $disk = Storage::disk(config('filament.default_filesystem_disk'));

                            abort_unless($disk->exists($documento->ruta), 404);

                            return response()->streamDownload(
                                fn () => print ($disk->get($documento->ruta)),
                                basename($documento->ruta)
                            );
                        }),
                    Action::make('editarDocumentoPersonal')
                        ->modalHeading('Editar descripción')
                        ->modalWidth('md')
                        ->modalSubmitActionLabel('Guardar')
                        ->visible(fn () => auth()->user()?->puede('editar'))
                        ->fillForm(fn (array $arguments): array => ['nombre' => $this->documentoPersonal($arguments)->nombre])
                        ->schema([
                            TextInput::make('nombre')
                                ->label('Descripción')
                                ->required()
                                ->maxLength(100)
                                ->extraInputAttributes(['data-enter-nav' => 'true']),
                        ])
                        ->action(function (array $arguments, array $data): void {
                            $this->documentoPersonal($arguments)->update(['nombre' => $data['nombre']]);

                            Notification::make()->title('Descripción actualizada')->success()->send();
                        }),
                    Action::make('eliminarDocumentoPersonal')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->modalHeading('¿Seguro que desea eliminar el documento:')
                        ->modalDescription(fn (array $arguments): HtmlString => new HtmlString(
                            '<strong style="font-size:1rem;">'.e($this->documentoPersonal($arguments)->nombre ?: basename($this->documentoPersonal($arguments)->ruta)).'</strong>?'
                        ))
                        ->modalSubmitActionLabel('Eliminar')
                        ->modalFooterActionsAlignment(Alignment::Start)
                        ->extraModalWindowAttributes(['class' => 'netley-eliminar-personal'])
                        ->visible(fn () => auth()->user()?->puede('editar'))
                        ->action(function (array $arguments): void {
                            $documento = $this->documentoPersonal($arguments);

                            Storage::disk(config('filament.default_filesystem_disk'))->delete($documento->ruta);
                            $documento->delete();

                            Notification::make()->title('Documento eliminado')->success()->send();
                        }),
                ])
                ->modalContent(fn (): View => view('filament.admin.personal.documentos-personal', [
                    'documentos' => $this->getRecord()->documentos()->orderBy('id')->get(),
                    'puedeEditar' => (bool) auth()->user()?->puede('editar'),
                ])),
            // Desde la ficha, al deshabilitar/habilitar vuelve a Vigentes.
            PersonalActions::toggleEstado()
                ->hidden(fn () => $this->getRecord()->trashed())
                ->after(fn () => $this->redirect(PersonalResource::getUrl('index', ['tab' => 'vigentes']))),
            PersonalActions::asignarUsuario()->hidden(fn () => $this->getRecord()->trashed()),
            PersonalActions::asignarAbogados()->hidden(fn () => $this->getRecord()->trashed()),
            // Retirado: "Habilitar" lo restaura y vuelve a Vigentes.
            PersonalActions::restaurarYHabilitar()
                ->visible(fn () => $this->getRecord()->trashed() && auth()->user()?->puede('editar'))
                ->successRedirectUrl(fn (): string => PersonalResource::getUrl('index', ['tab' => 'vigentes'])),
            DeleteAction::make()
                // "Retirar" (borrado lógico): el personal pasa a Retirados.
                ->label('Retirar')
                ->modalHeading('¿Seguro que desea retirar a:')
                ->modalDescription(fn (Personal $record) => PersonalActions::nombreDestacado($record))
                ->modalSubmitActionLabel('Retirar')
                ->successNotificationTitle('Personal retirado')
                // Al requerir confirmación, Filament centra los botones
                // invirtiendo su orden (Cancelar antes que Eliminar) — se fija
                // el orden normal (Eliminar primero) y el centrado visual se
                // hace por CSS (ver .netley-eliminar-personal en admin-theme.css).
                ->modalFooterActionsAlignment(Alignment::Start)
                ->extraModalWindowAttributes(['class' => 'netley-eliminar-personal'])
                ->visible(fn () => auth()->user()?->puede('eliminar') && ! $this->getRecord()->trashed())
                ->schema([
                    // Arranca con la fecha local, pero se puede cambiar
                    // libremente (hacia atrás o a futuro).
                    DatePicker::make('fecha_fin')
                        ->label('Fecha de retiro')
                        ->required()
                        ->default(now())
                        ->extraInputAttributes(['data-enter-nav' => 'true']),
                    TextInput::make('motivo_baja')
                        ->label('Motivo')
                        ->required()
                        ->extraInputAttributes(['data-enter-nav' => 'true']),
                ])
                // El motivo se guarda en la misma columna que usa
                // "Deshabilitar" (ver PersonalActions::toggleEstado()): al
                // eliminar, Personal::booted() ya deja estado=Inhabilitado
                // solo, acá solo falta sumarle el motivo antes de borrar.
                // Devuelve el resultado: si no, Filament lo toma como fallido
                // y no muestra el aviso ni redirige.
                ->using(function (Personal $record, array $data): bool {
                    $record->fecha_fin = $data['fecha_fin'];
                    $record->motivo_baja = $data['motivo_baja'];

                    return (bool) $record->delete();
                })
                ->successRedirectUrl(fn (): string => PersonalResource::getUrl('index', ['tab' => 'vigentes'])),
        ];
    }

    /**
     * En la vista normal todos los campos están deshabilitados (ver
     * PersonalForm), así que no hay botones de guardado. En modo edición
     * (ver urlFicha()) se ofrecen "Guardar" y "Cancelar".
     *
     * Filament arma estos botones antes de ejecutar la acción del clic, así
     * que cambiar $this->editando en un clic no los actualiza: por eso
     * "Editar", "Cancelar" y "Guardar" recargan la ficha.
     */
    protected function getFormActions(): array
    {
        if ($this->editando) {
            return [
                $this->getSaveFormAction()->label('Guardar'),
                Action::make('cancelarEdicion')
                    ->label('Cancelar')
                    ->color('gray')
                    ->action(fn () => $this->redirect($this->urlFicha())),
            ];
        }

        return [];
    }

    /**
     * "Fecha de inicio" solo pide la fecha en el formulario; si se cambia,
     * se conserva la hora que ya tenía el registro (no se pide ni se
     * reemplaza por la hora de esta edición).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['fecha_inicio'] ?? null) && $this->record->fecha_inicio) {
            $data['fecha_inicio'] = Carbon::parse($data['fecha_inicio'])
                ->setTimeFrom($this->record->fecha_inicio);
        }

        // "Cargo principal" no se pide en el formulario: se recalcula desde
        // "Profesión". Si no se puede derivar (sin profesiones o solo "Otros"),
        // se conserva el valor que ya tenía (p. ej. legados como 'Directora' o
        // 'Gerente', o un 'Abogado' importado sin `cargo`), para no sacar a
        // nadie de las listas de abogados solo por editar otro dato.
        $data['profesion'] = PersonalEspecialidades::profesionPrincipal($data['cargo'] ?? [])
            ?? $this->record->profesion;

        return $data;
    }

    /** Ficha de esta persona; con $editar abre directo en modo edición. */
    protected function urlFicha(bool $editar = false): string
    {
        $url = $this->getResourceUrl('edit', ['record' => $this->getRecord()]);

        return $editar ? $url.'?editar=1' : $url;
    }
}
