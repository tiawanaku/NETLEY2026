<?php

namespace App\Filament\Admin\Resources\Personals\Pages;

use App\Filament\Admin\Resources\Personals\Actions\PersonalActions;
use App\Filament\Admin\Resources\Personals\PersonalResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

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
            $data['fecha_inicio'] = \Illuminate\Support\Carbon::parse($data['fecha_inicio'])
                ->setTimeFrom($this->record->fecha_inicio);
        }

        return $data;
    }
}
