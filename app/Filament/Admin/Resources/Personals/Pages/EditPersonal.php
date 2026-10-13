<?php

namespace App\Filament\Admin\Resources\Personals\Pages;

use App\Filament\Admin\Resources\Personals\Actions\PersonalActions;
use App\Filament\Admin\Resources\Personals\PersonalResource;
use App\Support\PersonalEspecialidades;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;

class EditPersonal extends EditRecord
{
    protected static string $resource = PersonalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PersonalActions::toggleEstado(),
            PersonalActions::asignarUsuario(),
            PersonalActions::asignarAbogados(),
            DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
        ];
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
}
