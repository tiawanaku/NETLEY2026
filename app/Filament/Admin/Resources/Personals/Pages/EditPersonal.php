<?php

namespace App\Filament\Admin\Resources\Personals\Pages;

use App\Filament\Admin\Resources\Personals\Actions\PersonalActions;
use App\Filament\Admin\Resources\Personals\PersonalResource;
use App\Models\Personal;
use App\Support\PersonalEspecialidades;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Carbon;

class EditPersonal extends EditRecord
{
    protected static string $resource = PersonalResource::class;

    public bool $editando = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->editando = request()->boolean('editar') && auth()->user()?->puede('editar');
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editarPersonal')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => ! $this->editando && auth()->user()?->puede('editar'))
                ->action(fn () => $this->redirect($this->urlFicha(editar: true))),
            PersonalActions::toggleEstado(),
            PersonalActions::asignarUsuario(),
            PersonalActions::asignarAbogados(),
            DeleteAction::make()
                ->label('Eliminar')
                ->modalHeading('¿Seguro que desea eliminar a:')
                ->modalDescription(fn (Personal $record) => PersonalActions::nombreDestacado($record))
                ->modalSubmitActionLabel('Eliminar')
                // Al requerir confirmación, Filament centra los botones
                // invirtiendo su orden (Cancelar antes que Eliminar) — se fija
                // el orden normal (Eliminar primero) y el centrado visual se
                // hace por CSS (ver .netley-eliminar-personal en admin-theme.css).
                ->modalFooterActionsAlignment(Alignment::Start)
                ->extraModalWindowAttributes(['class' => 'netley-eliminar-personal'])
                ->visible(fn () => auth()->user()?->puede('eliminar'))
                ->schema([
                    TextInput::make('motivo_baja')
                        ->label('Motivo')
                        ->required()
                        ->extraInputAttributes(['data-enter-nav' => 'true']),
                ])
                // El motivo se guarda en la misma columna que usa
                // "Deshabilitar" (ver PersonalActions::toggleEstado()): al
                // eliminar, Personal::booted() ya deja estado=Inhabilitado
                // solo, acá solo falta sumarle el motivo antes de borrar.
                ->using(function (Personal $record, array $data): void {
                    $record->motivo_baja = $data['motivo_baja'];
                    $record->delete();
                }),
        ];
    }

    /**
     * En la vista normal todos los campos están deshabilitados (ver
     * PersonalForm), así que no hay botones de guardado. En modo edición
     * (ver urlFicha()) se ofrecen "Guardar cambios" y "Cancelar edición".
     *
     * Filament arma estos botones antes de ejecutar la acción del clic, así
     * que cambiar $this->editando en un clic no los actualiza: por eso
     * "Editar", "Cancelar edición" y "Guardar cambios" recargan la ficha.
     */
    protected function getFormActions(): array
    {
        if ($this->editando) {
            return [
                $this->getSaveFormAction()->label('Guardar cambios'),
                Action::make('cancelarEdicion')
                    ->label('Cancelar edición')
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
