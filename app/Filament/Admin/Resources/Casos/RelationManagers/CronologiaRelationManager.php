<?php

namespace App\Filament\Admin\Resources\Casos\RelationManagers;

use App\Models\Caso;
use App\Support\CronologiaCaso;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Pestaña "Cronología" de la ficha del caso: una línea de tiempo con todo lo
 * que pasó en el caso (ver App\Support\CronologiaCaso). Es una pestaña de
 * solo lectura: no muestra una tabla, solo la línea de tiempo (content()).
 */
class CronologiaRelationManager extends RelationManager
{
    // Requerido por RelationManager; la vista no usa esta relación sola,
    // junta varias (ver CronologiaCaso::eventos()).
    protected static string $relationship = 'seguimientos';

    protected static ?string $title = 'Cronología';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clock';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.admin.casos.cronologia')
                ->viewData(function (): array {
                    /** @var Caso $caso */
                    $caso = $this->getOwnerRecord()->fresh();

                    return ['eventos' => CronologiaCaso::eventos($caso)];
                }),
        ]);
    }
}
