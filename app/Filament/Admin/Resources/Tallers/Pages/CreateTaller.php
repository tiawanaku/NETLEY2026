<?php

namespace App\Filament\Admin\Resources\Tallers\Pages;

use App\Filament\Admin\Resources\Tallers\TallerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaller extends CreateRecord
{
    use \App\Filament\Admin\Concerns\MigasSimples;

    protected static string $resource = TallerResource::class;
}
