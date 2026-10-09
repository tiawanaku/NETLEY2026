<?php

namespace App\Filament\Admin\Resources\Consultas\RelationManagers;

use App\Filament\Admin\Forms\Components\DelitoSelect;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RespuestasRelationManager extends RelationManager
{
    protected static string $relationship = 'respuestas';

    protected static ?string $title = 'Respuestas';

    public function form(Schema $schema): Schema
    {
        return $schema->inlineLabel()->columns(1)->components([
            Select::make('personal_id')
                ->label('Respondido por')
                ->relationship('personal', 'nombres')
                ->searchable()
                ->required(),
            Textarea::make('respuesta')->required()->rows(3),
            ...DelitoSelect::make(
                areaField: 'designacion',
                delitoField: 'delito_id',
                areaLabel: 'Materia legal',
                required: false,
            ),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('respuesta')
            ->columns([
                TextColumn::make('personal.nombres')->label('Personal'),
                TextColumn::make('designacion')->badge(),
                TextColumn::make('delito.delito')->label('Delito')->limit(30)->placeholder('-'),
                TextColumn::make('respuesta')->limit(60),
                TextColumn::make('fecha_respuesta')->dateTime(),
            ])
            ->defaultSort('fecha_respuesta', 'desc')
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
