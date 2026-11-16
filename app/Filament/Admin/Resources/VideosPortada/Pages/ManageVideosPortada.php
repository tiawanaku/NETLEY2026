<?php

namespace App\Filament\Admin\Resources\VideosPortada\Pages;

use App\Filament\Admin\Resources\VideosPortada\VideoPortadaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVideosPortada extends ManageRecords
{
    use \App\Filament\Admin\Concerns\MigasSimples;

    protected static string $resource = VideoPortadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
