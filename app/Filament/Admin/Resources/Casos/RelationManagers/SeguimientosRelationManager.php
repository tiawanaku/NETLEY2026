<?php

namespace App\Filament\Admin\Resources\Casos\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeguimientosRelationManager extends RelationManager
{
    protected static string $relationship = 'seguimientos';

    protected static ?string $title = 'Seguimiento del proceso';

    public function form(Schema $schema): Schema
    {
        return $schema->inlineLabel()->columns(1)->components([
            DateTimePicker::make('fecha_seguimiento')->default(now())->required(),
            TextInput::make('responsable'),
            TextInput::make('etapa_proceso')->label('Etapa del proceso'),
            Textarea::make('observaciones'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('etapa_proceso')
            ->columns([
                TextColumn::make('fecha_seguimiento')->dateTime(),
                TextColumn::make('etapa_proceso')->label('Etapa'),
                TextColumn::make('responsable'),
                TextColumn::make('observaciones')->limit(50),
            ])
            ->defaultSort('fecha_seguimiento', 'desc')
            ->headerActions([CreateAction::make()])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                EditAction::make()->visible(fn () => auth()->user()?->puede('editar')),
                DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
            ]);
    }
}
