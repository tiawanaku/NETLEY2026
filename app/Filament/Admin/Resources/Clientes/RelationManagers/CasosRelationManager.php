<?php

namespace App\Filament\Admin\Resources\Clientes\RelationManagers;

use App\Enums\EstadoCaso;
use App\Filament\Admin\Forms\Components\DelitoSelect;
use App\Filament\Admin\Resources\Casos\CasoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CasosRelationManager extends RelationManager
{
    protected static string $relationship = 'casos';

    protected static ?string $title = 'Casos';

    public function form(Schema $schema): Schema
    {
        return $schema->inlineLabel()->columns(1)->components([
            ...DelitoSelect::make(areaField: 'especialidad', delitoField: 'delito_id'),
            TextInput::make('iguala')->numeric()->default(0)->required(),
            DatePicker::make('fecha_inicio')->required()->default(now()),
            Select::make('estado')->options(EstadoCaso::class)->default(EstadoCaso::ActivoPendiente)->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('especialidad')->label('Materia legal')->badge()->state(fn ($record) => $record->especialidad ?? $record->materia_texto),
                TextColumn::make('delito.delito')->label('Delito')->limit(40)->placeholder(fn ($record) => $record->delito_texto ?: '-'),
                TextColumn::make('estado')->badge(),
                TextColumn::make('saldo')->money('BOB'),
                TextColumn::make('fecha_fin')->date()->label('Vencimiento'),
            ])
            ->headerActions([
                CreateAction::make()->label('Nuevo proceso'),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                // A diferencia de un EditAction normal (que abriría un modal
                // con solo los campos de este formulario reducido), esto
                // navega a la ficha completa del caso (CasoResource), con
                // todos los datos desglosados y las pestañas de seguimiento,
                // documentación, pagos, fiscalías y juzgados.
                Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => CasoResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
