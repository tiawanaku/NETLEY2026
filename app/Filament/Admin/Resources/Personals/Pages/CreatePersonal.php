<?php

namespace App\Filament\Admin\Resources\Personals\Pages;

use App\Filament\Admin\Resources\Personals\PersonalResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreatePersonal extends CreateRecord
{
    protected static string $resource = PersonalResource::class;

    protected static ?string $title = 'Agregar personal';

    /**
     * "Fecha de inicio" solo pide la fecha en el formulario; la hora no se le
     * pregunta al usuario, se registra sola con el momento real de alta.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (filled($data['fecha_inicio'] ?? null)) {
            $data['fecha_inicio'] = Carbon::parse($data['fecha_inicio'])->setTimeFrom(now());
        }

        return $data;
    }

    /** Botones únicamente al pie del formulario: Guardar y Cancelar. */
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction()->label('Cancelar'),
        ];
    }

    /** Después de registrar, volver al listado de Personal en vez de quedarse en la edición. */
    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('index');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Guardar')
            ->submit(null)
            ->action(fn () => $this->create())
            ->requiresConfirmation()
            ->modalHeading('¿Agregar este personal?')
            ->modalDescription('Revisa los datos antes de confirmar. Puedes presionar Enter para confirmar.')
            ->modalSubmitActionLabel('Agregar personal')
            ->modalCancelAction(false)
            ->extraModalWindowAttributes(['data-enter-confirm' => 'true'])
            ->extraAttributes(['data-enter-submit' => 'true']);
    }
}
