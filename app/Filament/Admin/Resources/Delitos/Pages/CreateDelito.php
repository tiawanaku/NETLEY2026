<?php

namespace App\Filament\Admin\Resources\Delitos\Pages;

use App\Filament\Admin\Resources\Delitos\DelitoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDelito extends CreateRecord
{
    use \App\Filament\Admin\Concerns\MigasSimples;

    protected static string $resource = DelitoResource::class;
}
