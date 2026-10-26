<?php

namespace App\Filament\Admin\Resources\Clientes\RelationManagers;

use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Filament\Admin\Support\ClienteCasoWizard;
use App\Models\Caso;
use App\Models\Cliente;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CasosRelationManager extends RelationManager
{
    protected static string $relationship = 'casos';

    protected static ?string $title = 'Casos';

    /**
     * Mismos campos que los pasos "Proceso" y "Pago y plan de cuotas" del
     * wizard de Cliente Ejecutivo (ClienteCasoWizard) — aquí en un solo
     * formulario, sin wizard, porque el cliente ya existe (ver crearCaso()
     * más abajo, que hace el guardado real).
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel()
            ->columns(2)
            ->components([
                ...ClienteCasoWizard::camposProceso(),
                ...ClienteCasoWizard::camposPago(),
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
                CreateAction::make()
                    ->label('Nuevo proceso')
                    ->modalSubmitActionLabel('Guardar')
                    ->createAnother(false)
                    // El guardado real no es un simple $relationship->create():
                    // replica la misma lógica financiera (Iguala Netley, pago
                    // del anticipo, plan de cuotas) que ClienteCasoWizard usa
                    // al dar de alta un cliente nuevo, pero sobre el cliente
                    // ya existente de esta ficha.
                    ->using(function (array $data): Caso {
                        /** @var Cliente $cliente */
                        $cliente = $this->getOwnerRecord();

                        return ClienteCasoWizard::crearCaso($cliente, $data);
                    }),
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
