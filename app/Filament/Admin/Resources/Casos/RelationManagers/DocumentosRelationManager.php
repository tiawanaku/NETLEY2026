<?php

namespace App\Filament\Admin\Resources\Casos\RelationManagers;

use App\Enums\TipoDocumento;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';

    protected static ?string $title = 'Documentos';

    public function form(Schema $schema): Schema
    {
        return $schema->inlineLabel()->columns(1)->components([
            TextInput::make('descripcion')->maxLength(100),
            Select::make('tipo')
                ->label('Tipo')
                ->options(TipoDocumento::class)
                ->native(false)
                ->live()
                ->required()
                ->afterStateUpdated(fn ($set) => $set('tipo_detalle', null)),
            TextInput::make('tipo_detalle')
                ->label(fn (Get $get) => $get('tipo') === TipoDocumento::Fojas ? 'Cantidad de fojas' : 'Especifique')
                ->numeric(fn (Get $get) => $get('tipo') === TipoDocumento::Fojas)
                ->visible(fn (Get $get) => in_array($get('tipo'), [TipoDocumento::Fojas, TipoDocumento::Otro], true))
                ->required(fn (Get $get) => in_array($get('tipo'), [TipoDocumento::Fojas, TipoDocumento::Otro], true)),
            FileUpload::make('ruta')->label('Archivo')->directory('documentos-clientes')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('descripcion')
            ->columns([
                TextColumn::make('descripcion')->searchable(),
                TextColumn::make('tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => $state === TipoDocumento::Fojas && filled($record->tipo_detalle)
                        ? $state->getLabel().' ('.$record->tipo_detalle.')'
                        : $state?->getLabel()),
                TextColumn::make('fecha_origen')->dateTime(),
                IconColumn::make('subido_por_cliente')
                    ->label('Subido por el cliente')
                    ->boolean()
                    ->trueIcon('heroicon-o-user')
                    ->falseIcon('heroicon-o-briefcase')
                    ->trueColor('info')
                    ->falseColor('gray'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir documentación al expediente')
                    ->mutateDataUsing(function (array $data): array {
                        $data['cliente_id'] = $this->getOwnerRecord()->cliente_id;
                        $data['fecha_origen'] = now();

                        return $data;
                    }),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
            ]);
    }
}
