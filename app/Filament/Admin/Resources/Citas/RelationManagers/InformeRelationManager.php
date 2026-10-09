<?php

namespace App\Filament\Admin\Resources\Citas\RelationManagers;

use App\Models\InformeCita;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InformeRelationManager extends RelationManager
{
    protected static string $relationship = 'informe';

    protected static ?string $title = 'Informe de cita';

    public function form(Schema $schema): Schema
    {
        return $schema->inlineLabel()->columns(1)->components([
            DatePicker::make('fecha_informe')->default(now())->required(),
            TextInput::make('forma_ingreso'),
            TextInput::make('nombre_colegio')->label('Colegio (si es taller)'),
            TextInput::make('responsable'),
            TextInput::make('acciones'),
            TextInput::make('anticipo')->numeric()->prefix('Bs.')->default(0),
            TextInput::make('iguala')->numeric()->prefix('Bs.')->default(0),
            Textarea::make('detalle'),
            Textarea::make('redaccion')->label('Redacción / informe completo'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('fecha_informe')
            ->columns([
                TextColumn::make('fecha_informe')->date(),
                TextColumn::make('responsable'),
                TextColumn::make('acciones'),
                TextColumn::make('detalle')->limit(50),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn (InformeCita $record) => response()->streamDownload(
                        fn () => print (Pdf::loadView('pdf.informe-cita', [
                            'informe' => $record,
                            'cita' => $this->getOwnerRecord(),
                        ])->output()),
                        "informe-cita-{$record->id}.pdf"
                    )),
                EditAction::make()->visible(fn () => auth()->user()?->puede('editar')),
                DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
            ]);
    }
}
