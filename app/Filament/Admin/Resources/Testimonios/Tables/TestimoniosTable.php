<?php

namespace App\Filament\Admin\Resources\Testimonios\Tables;

use App\Enums\EstadoAprobacion;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class TestimoniosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->searchable(),
                TextColumn::make('testimonio')->limit(60),
                TextColumn::make('calificacion')->numeric()->sortable(),
                TextColumn::make('fecha')->dateTime()->sortable(),
                TextColumn::make('estado')->badge(),
                IconColumn::make('visible')->boolean()->label('En sitio público'),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                SelectFilter::make('estado')->options(EstadoAprobacion::class),
                TernaryFilter::make('visible'),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                EditAction::make()->visible(fn () => auth()->user()?->puede('editar')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('aprobar')
                        ->label('Aprobar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Collection $records): void {
                            $records->each->update(['estado' => EstadoAprobacion::Aprobado, 'visible' => true, 'fecha_modificacion' => now(), 'modificado_por' => auth()->user()?->username]);
                            Notification::make()->title('Testimonios aprobados')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('rechazar')
                        ->label('Rechazar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (Collection $records): void {
                            $records->each->update(['estado' => EstadoAprobacion::Rechazado, 'visible' => false, 'fecha_modificacion' => now(), 'modificado_por' => auth()->user()?->username]);
                            Notification::make()->title('Testimonios rechazados')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
                ]),
            ]);
    }
}
