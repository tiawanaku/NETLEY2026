<?php

namespace App\Filament\Admin\Resources\Pagos\Tables;

use App\Enums\Especialidad;
use App\Models\Pago;
use App\Models\Personal;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PagosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nro_recibo')->label('Recibo #')->searchable(),
                TextColumn::make('cliente.nombre_completo')->label('Cliente')->searchable(),
                TextColumn::make('caso_id')->label('Caso')->formatStateUsing(fn ($state) => "#{$state}"),
                TextColumn::make('caso.especialidad')->label('Materia')->badge(),
                TextColumn::make('nro_cuota')->label('Cuota #'),
                TextColumn::make('monto')->money('BOB')->summarize(
                    Sum::make()->label('Total')->money('BOB')
                ),
                TextColumn::make('fecha_pago')->date()->sortable(),
                TextColumn::make('sucursal')->badge(),
                TextColumn::make('registrado_por'),
            ])
            ->defaultSort('fecha_pago', 'desc')
            ->filters([
                SelectFilter::make('sucursal')
                    ->options(['LA PAZ' => 'La Paz', 'SANTA CRUZ' => 'Santa Cruz', 'COCHABAMBA' => 'Cochabamba']),
                SelectFilter::make('especialidad')
                    ->label('Materia')
                    ->options(Especialidad::class)
                    ->query(fn (Builder $query, array $data) => $data['value']
                        ? $query->whereHas('caso', fn ($q) => $q->where('especialidad', $data['value']))
                        : $query),
                SelectFilter::make('personal')
                    ->label('Abogado')
                    ->options(fn () => Personal::query()->pluck('nombres', 'id'))
                    ->query(fn (Builder $query, array $data) => $data['value']
                        ? $query->whereHas('caso.personal', fn ($q) => $q->where('personal.id', $data['value']))
                        : $query),
                Filter::make('periodo')
                    ->schema([
                        Select::make('anio')
                            ->label('Año')
                            ->options(fn () => Pago::query()->selectRaw('YEAR(fecha_pago) as y')->distinct()->pluck('y', 'y')),
                        Select::make('mes')
                            ->label('Mes')
                            ->options([
                                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                                7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['anio'] ?? null, fn ($q, $anio) => $q->whereYear('fecha_pago', $anio))
                            ->when($data['mes'] ?? null, fn ($q, $mes) => $q->whereMonth('fecha_pago', $mes));
                    }),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                Action::make('recibo')
                    ->label('Recibo PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn (Pago $record) => response()->streamDownload(
                        fn () => print (Pdf::loadView('pdf.recibo-pago', ['pago' => $record->load(['cliente', 'caso'])])->output()),
                        "recibo-{$record->nro_recibo}.pdf"
                    )),
            ]);
    }
}
