<?php

namespace App\Filament\Admin\Resources\Casos\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OtrosSeguimientosRelationManager extends RelationManager
{
    protected static string $relationship = 'otrosSeguimientos';

    protected static ?string $title = 'Otras instancias';

    public function form(Schema $schema): Schema
    {
        return $schema->inlineLabel()->columns(1)->components([
            TextInput::make('nombre_instancia')->label('Instancia')->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('num_caso')->label('N° de caso')->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('nombre_contacto')->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('numero_contacto')->extraInputAttributes(['data-enter-nav' => 'true']),
            DatePicker::make('fecha_inicio')->extraInputAttributes(['data-enter-nav' => 'true']),
            DatePicker::make('fecha_fin')->extraInputAttributes(['data-enter-nav' => 'true']),
            Textarea::make('detalles')->extraInputAttributes(['data-enter-nav' => 'true']),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre_instancia')
            ->columns([
                TextColumn::make('nombre_instancia')->label('Instancia'),
                TextColumn::make('nombre_contacto'),
                TextColumn::make('num_caso'),
            ])
            ->headerActions([CreateAction::make()])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn () => auth()->user()?->puede('editar')),
                DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
            ]);
    }
}
