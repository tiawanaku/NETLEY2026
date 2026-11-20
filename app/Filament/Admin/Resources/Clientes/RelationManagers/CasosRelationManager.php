<?php

namespace App\Filament\Admin\Resources\Clientes\RelationManagers;

use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Filament\Admin\Resources\Clientes\ClienteResource;
use App\Models\Caso;
use App\Filament\Admin\Support\ClienteCasoWizard;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CasosRelationManager extends RelationManager
{
    protected static string $relationship = 'casos';

    protected static ?string $title = 'Datos del proceso';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-briefcase';

    /** Abierta desde "Casos cerrados": solo los procesos cerrados (ver EditCliente). */
    public bool $soloCerrados = false;

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
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['personal', 'delito', 'delitosDenunciados', 'planesPago', 'casosDerivados'])
                // Desde "Casos cerrados": solo los cerrados; en la ficha normal,
                // todos menos los cerrados (esos se ven en "Casos cerrados").
                ->when(
                    $this->soloCerrados,
                    fn ($query) => $query->where('estado', \App\Enums\EstadoCaso::Cerrado),
                    fn ($query) => $query->where('estado', '!=', \App\Enums\EstadoCaso::Cerrado),
                ))
            // Orden pedido; mismas columnas que la lista de Clientes ejecutivos.
            ->columns([
                // "Caso" y "Detalle" de Datos del proceso (NUREJ / CUD / otro).
                TextColumn::make('caso_texto')
                    ->label('Caso')
                    ->state(fn (Caso $record): ?string => $record->caso_texto)
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('especialidad')->label('Materia legal')->badge()->state(fn ($record) => $record->especialidad ?? $record->materia_texto),
                // Delito principal y los denunciados, uno debajo del otro.
                TextColumn::make('tipo_proceso')
                    ->label('Tipo de proceso')
                    ->state(fn (Caso $record): array => $record->tiposProceso())
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextColumn::make('abogados')
                    ->label('Abogado')
                    ->state(fn (Caso $record): array => $record->personal->pluck('nombre_completo')->all())
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextColumn::make('fecha_inicio')->label('Fecha inicio')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('fecha_fin')->label('Fecha fin')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('estado')->badge(),
                // Pago, vencimiento y documento pendiente de este caso (ver IndicadoresCliente).
                TextColumn::make('alerta')
                    ->label('Alerta')
                    ->html()
                    ->state(fn (Caso $record) => \App\Filament\Admin\Support\IndicadoresCliente::renderCaso($record))
                    ->placeholder('—'),
            ])
            // Sin botón de crear acá: "Añadir caso" está al lado de las
            // pestañas de la ficha del cliente (EditCliente::anadirCasoAction()).
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
                    // Pendiente de costas: en su lugar va "Iniciar costas y costos";
                    // cerrado sin costas: los dos.
                    ->visible(fn (Caso $record): bool => ! $record->requiereCostasYCostos() || $record->estado === \App\Enums\EstadoCaso::Cerrado)
                    ->url(fn ($record) => CasoResource::getUrl('edit', ['record' => $record])),
                // Caso cerrado y ganado con patrocinio HiH: en lugar de "Ver",
                // mientras no se inicie, abre "Nuevo proceso" en la ficha del
                // cliente (EditCliente::anadirCasoAction(), con el caso de
                // origen) para las costas y costos.
                Action::make('iniciarCostas')
                    ->label('Iniciar costas y costos')
                    ->icon('heroicon-o-scale')
                    ->color('success')
                    ->visible(fn (Caso $record): bool => $record->requiereCostasYCostos() && CasoResource::canCreate())
                    ->url(fn (Caso $record): string => ClienteResource::getUrl('edit', ['record' => $record->cliente_id])
                        .'?'.http_build_query(['action' => 'anadirCaso', 'actionArguments' => ['origen' => $record->getKey()]])),
                // Ya iniciado: al lado de "Ver", lleva directo a su proceso de
                // costas y costos (el más reciente, si hubiera más de uno).
                Action::make('verCostas')
                    ->label('Costas y costos')
                    ->icon('heroicon-o-scale')
                    ->color('success')
                    ->visible(fn (Caso $record): bool => $record->casosDerivados->isNotEmpty())
                    ->url(fn (Caso $record): string => CasoResource::getUrl('edit', ['record' => $record->casosDerivados->sortByDesc('id')->first()])),
            ]);
    }
}
