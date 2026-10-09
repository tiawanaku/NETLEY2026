<?php

namespace App\Filament\Admin\Resources\Casos\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FiscaliasRelationManager extends RelationManager
{
    protected static string $relationship = 'fiscalias';

    protected static ?string $title = 'Fiscalías';

    /**
     * "Vigente" va primero y siempre queda editable (cualquier usuario puede
     * marcar una fiscalía como no vigente); el resto de campos se bloquean
     * en la ventana de "Ver" ($lockOthers) y solo quedan editables para
     * Master/Administrador a través de "Editar".
     *
     * @return array<int, Component>
     */
    protected static function fields(bool $lockOthers = false): array
    {
        return [
            Toggle::make('vigente')->default(true),
            TextInput::make('num_caso')->label('N° de caso (Ministerio Público)')->disabled($lockOthers)->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('fiscalia_num')->label('Fiscalía')->disabled($lockOthers)->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('nombre_fiscal')->disabled($lockOthers)->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('telefono_fiscal')->disabled($lockOthers)->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('investigador')->disabled($lockOthers)->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('telefono_investigador')->disabled($lockOthers)->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('auxiliar')->disabled($lockOthers)->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('telefono_auxiliar')->disabled($lockOthers)->extraInputAttributes(['data-enter-nav' => 'true']),
            DatePicker::make('fecha_inicio')->disabled($lockOthers)->extraInputAttributes(['data-enter-nav' => 'true']),
            DatePicker::make('fecha_fin')->disabled($lockOthers)->extraInputAttributes(['data-enter-nav' => 'true']),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->inlineLabel()->columns(1)->components(self::fields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('fiscalia_num')
            ->columns([
                TextColumn::make('fiscalia_num')->label('Fiscalía'),
                TextColumn::make('nombre_fiscal'),
                TextColumn::make('num_caso'),
                IconColumn::make('vigente')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->schema(fn () => self::fields(lockOthers: true))
                    ->fillForm(fn ($record) => $record->attributesToArray())
                    ->modalSubmitActionLabel('Guardar')
                    ->action(fn ($record, array $data) => $record->update([
                        'vigente' => (bool) ($data['vigente'] ?? $record->vigente),
                    ])),
                EditAction::make()->visible(fn () => auth()->user()?->puede('editar')),
                DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
            ]);
    }
}
