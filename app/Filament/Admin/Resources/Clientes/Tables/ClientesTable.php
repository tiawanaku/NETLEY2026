<?php

namespace App\Filament\Admin\Resources\Clientes\Tables;

use App\Filament\Admin\Support\FiltrosContacto;
use App\Filament\Admin\Support\IndicadoresCliente;
use App\Filament\Admin\Support\TelefonoColumna;
use App\Models\Cliente;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkAction;
use App\Filament\Admin\Support\Eliminacion;
use Filament\Notifications\Notification;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Abogado(s) y fechas del proceso salen del caso más reciente;
            // los indicadores ($/balanza, ver IndicadoresCliente) miran
            // TODOS los casos abiertos, con sus cuotas ya precargadas para
            // no disparar una consulta por fila.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['ultimoCaso.personal', 'ultimoCaso.delito', 'ultimoCaso.delitosDenunciados', 'casos.planesPago']))
            // Orden pedido. Caso, tipo de proceso, abogado y fechas: del caso
            // más reciente del cliente.
            ->columns([
                TextColumn::make('nro_cliente')
                    ->label('ID cliente')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->searchable(['nombres', 'ap_paterno', 'ap_materno'])
                    ->sortable(['nombres']),
                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->formatStateUsing(fn (?string $state) => TelefonoColumna::html($state))
                    ->html()
                    ->searchable(),
                // "Caso" y "Detalle" de Datos del proceso (NUREJ / CUD / otro).
                TextColumn::make('caso')
                    ->label('Caso')
                    ->state(fn (Cliente $record): ?string => $record->ultimoCaso?->caso_texto)
                    ->wrap()
                    ->placeholder('—'),
                // Delito principal y los denunciados, uno debajo del otro.
                TextColumn::make('tipo_proceso')
                    ->label('Tipo de proceso')
                    ->state(fn (Cliente $record): array => $record->ultimoCaso?->tiposProceso() ?? [])
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextColumn::make('abogados')
                    ->label('Abogado')
                    ->state(fn (Cliente $record): array => $record->ultimoCaso?->personal
                        ->map(fn ($personal) => $personal->nombre_completo)
                        ->all() ?? [])
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextColumn::make('ultimoCaso.fecha_inicio')
                    ->label('Fecha inicio')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('ultimoCaso.fecha_fin')
                    ->label('Fecha fin')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('casos_count')
                    ->label('N° de casos')
                    ->counts('casos')
                    ->badge()
                    ->sortable(),
                TextColumn::make('indicadores')
                    ->label('Alertas')
                    ->html()
                    ->state(fn (Cliente $record) => IndicadoresCliente::render($record)),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('sucursal')
                    ->options(['LA PAZ' => 'La Paz', 'SANTA CRUZ' => 'Santa Cruz', 'COCHABAMBA' => 'Cochabamba']),
                ...FiltrosContacto::make(),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                EditAction::make()->label('Editar')->visible(fn () => auth()->user()?->puede('editar')),
                // Borrado lógico (ver Cliente::booted()): sus casos pasan a
                // "Casos eliminados" y se pueden restaurar desde ahí.
                DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn () => auth()->user()?->puede('eliminar'))
                    ->modalHeading(fn (Cliente $record) => '¿Eliminar al cliente '.$record->nombre_completo.'?')
                    ->modalDescription(fn (Cliente $record) => ($n = $record->casos()->count()) > 0
                        ? 'Sus '.$n.' '.($n === 1 ? 'caso pasa' : 'casos pasan').' a "Casos eliminados", con sus pagos y documentos intactos. Se pueden restaurar desde ahí (el cliente vuelve con el caso).'
                        : 'El cliente sale del listado.')
                    ->modalWidth('lg')
                    ->fillForm(fn (): array => Eliminacion::valoresIniciales())
                    ->schema(Eliminacion::campos())
                    ->using(function (Cliente $record, array $data): bool {
                        Eliminacion::eliminar($record, $data);

                        return true;
                    })
                    ->modalSubmitActionLabel('Eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->modalFooterActionsAlignment(Alignment::Start)
                    ->extraModalWindowAttributes(['class' => 'netley-eliminar-personal'])
                    ->successNotificationTitle('Cliente eliminado'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Misma fecha y justificación para todos los seleccionados.
                    BulkAction::make('eliminarSeleccionados')
                        ->label('Eliminar seleccionados')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn () => auth()->user()?->puede('eliminar'))
                        ->modalHeading('¿Eliminar los clientes seleccionados?')
                        ->modalDescription('Sus casos pasan a "Casos eliminados" y se pueden restaurar desde ahí.')
                        ->modalSubmitActionLabel('Eliminar')
                        ->modalCancelActionLabel('Cancelar')
                        ->fillForm(fn (): array => Eliminacion::valoresIniciales())
                        ->schema(Eliminacion::campos())
                        ->action(function ($records, array $data): void {
                            $records->each(fn (Cliente $cliente) => Eliminacion::eliminar($cliente, $data));
                            Notification::make()->title('Clientes eliminados')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
