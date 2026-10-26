<?php

namespace App\Filament\Admin\Resources\Pagos\Pages;

use App\Filament\Admin\Resources\Pagos\PagoResource;
use App\Models\Pago;
use Filament\Resources\Pages\CreateRecord;

class CreatePago extends CreateRecord
{
    protected static string $resource = PagoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['nro_recibo'] = ((int) Pago::max('nro_recibo')) + 1;
        $data['nro_cuota'] = Pago::where('caso_id', $data['caso_id'])->count() + 1;
        $data['registrado_por'] = auth()->user()?->username;

        return $data;
    }
}
