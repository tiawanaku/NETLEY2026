<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Citas\Actions\AnularCitaAction;
use App\Filament\Admin\Resources\Citas\CitaResource;
use App\Filament\Admin\Support\FiltrosContacto;
use App\Models\Cita;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * "Agenda" del menú Cliente Ejecutivo: solo las citas de clientes
 * (citas.caso_id no nulo) — el menú "Agenda" general (CitaResource) mezcla
 * esas con las de consultas (leads) agendadas, ver citasQuery() de
 * AgendaCalendario. Reutiliza CitaResource para editar (abre su ficha
 * real), no duplica el formulario.
 */
class AgendaClientes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Agenda';

    protected static \UnitEnum|string|null $navigationGroup = 'Cliente Ejecutivo';

    protected static ?int $navigationSort = 36;

    protected static ?string $slug = 'clientes/agenda';

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('agenda') ?? false;
    }

    /** Sin botón propio en el menú lateral (a pedido); la página sigue accesible por URL directa. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return 'Agenda';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Cita::query()->whereNotNull('caso_id'))
            ->columns([
                TextColumn::make('fecha')->date()->sortable(),
                TextColumn::make('hora')->time(),
                TextColumn::make('personal.nombres')->label('Abogado(s)')->listWithLineBreaks()->limitList(2),
                TextColumn::make('caso.cliente.nombre_completo')->label('Cliente')->placeholder('-'),
                TextColumn::make('caso_id')->label('Caso')->badge()->formatStateUsing(fn ($state) => "#{$state}"),
                TextColumn::make('tipo'),
                TextColumn::make('detalle')->limit(40),
                IconColumn::make('anulada')->boolean(),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                SelectFilter::make('personal')
                    ->relationship('personal', 'nombres')
                    ->searchable(),
                ...FiltrosContacto::make(relacion: 'caso.cliente'),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                AnularCitaAction::make(),
                Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn () => auth()->user()?->puede('editar'))
                    ->url(fn (Cita $record) => CitaResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
