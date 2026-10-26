<?php

namespace App\Filament\Admin\Resources\Personals\Pages;

use App\Filament\Admin\Resources\Personals\Actions\PersonalActions;
use App\Filament\Admin\Resources\Personals\PersonalResource;
use App\Support\PersonalEspecialidades;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
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
                ->modalHeading('Eliminar '.$this->record->nombre_completo)
                ->modalSubmitActionLabel('Eliminar')
                ->visible(fn () => auth()->user()?->puede('eliminar')),
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
