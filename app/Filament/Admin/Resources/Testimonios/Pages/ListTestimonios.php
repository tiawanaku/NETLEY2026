<?php

namespace App\Filament\Admin\Resources\Testimonios\Pages;

use App\Filament\Admin\Resources\Testimonios\TestimonioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTestimonios extends ListRecords
{
    use \App\Filament\Admin\Concerns\MigasSimples;

    protected static string $resource = TestimonioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
