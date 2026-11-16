<?php

namespace App\Filament\Admin\Resources\Testimonios\Pages;

use App\Filament\Admin\Resources\Testimonios\TestimonioResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTestimonio extends CreateRecord
{
    use \App\Filament\Admin\Concerns\MigasSimples;

    protected static string $resource = TestimonioResource::class;
}
