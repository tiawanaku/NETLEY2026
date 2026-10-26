<?php

namespace App\Filament\Admin\Resources\Llamadas\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IntentosLlamadaRelationManager extends RelationManager
{
    protected static string $relationship = 'intentosLlamada';

    protected static ?string $title = 'Intentos de llamada';

    public function form(Schema $schema): Schema
    {
        return $schema->inlineLabel()->columns(1)->components([
            DateTimePicker::make('started_at')->label('Inicio')->default(now()),
            DateTimePicker::make('ended_at')->label('Fin'),
            TextInput::make('resultado'),
            Textarea::make('notas'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('resultado')
            ->columns([
                TextColumn::make('started_at')->label('Inicio')->dateTime(),
                TextColumn::make('resultado')->badge()->placeholder('-'),
                TextColumn::make('notas')->limit(50),
                TextColumn::make('creado_por'),
            ])
            ->defaultSort('started_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['creado_por'] = auth()->user()?->username;

                        return $data;
                    }),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
            ]);
    }
}
