<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\MigasDeNavegacion;
use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Models\Pago;
use Barryvdh\DomPDF\Facade\Pdf;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Pagos › Recibos anulados: los recibos que quedaron anulados al
 * corregir un pago ("Editar pago" en el Plan de pagos, ver
 * PlanPagoRecalculo::corregirPago()), con el recibo nuevo que los reemplazó
 * y el motivo. Solo consulta: no cuentan en ningún total.
 */
class RecibosAnulados extends Page implements HasTable
{
    use InteractsWithTable;
    use MigasDeNavegacion;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static ?string $navigationLabel = 'Recibos anulados';

    protected static \UnitEnum|string|null $navigationGroup = 'Pagos';

    protected static ?int $navigationSort = 42; // Pagos: Nuevo pago (40), Resumen de pagos (41), Recibos anulados.

    protected static ?string $slug = 'pagos/recibos-anulados';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('pagos') ?? false;
    }

    public function getTitle(): string
    {
        return 'Recibos anulados';
    }

    protected function migas(): array
    {
        return [['Pagos'], ['Recibos anulados']];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Pago::withoutGlobalScope('vigentes')
                ->where('anulado', true)
                ->with(['caso.cliente', 'reemplazadoPor']))
            ->columns([
                TextColumn::make('nro_recibo')
                    ->label('Recibo N°')
                    ->formatStateUsing(fn ($state) => str_pad((string) $state, 6, '0', STR_PAD_LEFT))
                    ->badge()
                    ->color('danger')
                    ->searchable(),
                TextColumn::make('fecha_pago')->label('Fecha del pago')->date('d/m/Y')->sortable(),
                TextColumn::make('caso.cliente.nombre_completo')->label('Cliente')->placeholder('—'),
                TextColumn::make('caso_id')
                    ->label('Caso')
                    ->formatStateUsing(fn ($state) => 'N° '.$state)
                    ->url(fn (Pago $record) => $record->caso_id ? CasoResource::getUrl('edit', ['record' => $record->caso_id]).'?relation=0' : null),
                TextColumn::make('concepto')->label('Concepto'),
                TextColumn::make('monto')
                    ->label('Monto Bs.')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0, '.', '')),
                TextColumn::make('anulado_at')->label('Anulado el')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('motivo_anulacion')->label('Motivo')->wrap()->limit(80)->placeholder('—'),
                TextColumn::make('reemplazadoPor.nro_recibo')
                    ->label('Reemplazado por')
                    ->formatStateUsing(fn ($state) => $state ? 'N° '.str_pad((string) $state, 6, '0', STR_PAD_LEFT) : '—')
                    ->badge()
                    ->color('success'),
                TextColumn::make('anulado_por')->label('Anulado por')->placeholder('—'),
            ])
            ->defaultSort('anulado_at', 'desc')
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                Action::make('imprimirAnulado')
                    ->label('Imprimir')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->action(fn (Pago $record) => response()->streamDownload(
                        fn () => print (Pdf::loadView('pdf.recibo-pago', ['pago' => $record->load(['cliente', 'caso', 'reemplazadoPor'])])->output()),
                        'recibo-'.$record->nro_recibo.'-anulado.pdf'
                    )),
            ])
            ->emptyStateHeading('No hay recibos anulados')
            ->emptyStateDescription('Cuando se corrige un pago con "Editar pago", el recibo anterior aparece aquí.');
    }
}
