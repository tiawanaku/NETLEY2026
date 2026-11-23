<?php

namespace App\Filament\Admin\Resources\Casos\RelationManagers;

use App\Filament\Admin\Support\EtapasProceso;
use App\Models\Caso;
use App\Models\Personal;
use App\Models\SeguimientoCaso;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Seguimiento del proceso: cada fila es una actuación reportada por el
 * abogado (fecha, etapa, instancia, qué se hizo y qué sigue). Arriba de la
 * tabla, un resumen muestra la etapa actual en una barra de avance, la
 * instancia vigente, el último movimiento y la próxima actuación.
 */
class SeguimientosRelationManager extends RelationManager
{
    protected static string $relationship = 'seguimientos';

    protected static ?string $title = 'Seguimiento del proceso';

    protected static ?string $modelLabel = 'seguimiento';

    protected function caso(): Caso
    {
        /** @var Caso */
        return $this->getOwnerRecord();
    }

    /** Abogado por defecto: el usuario actual si es del caso; si no, el primero del caso. */
    protected function abogadoPorDefecto(): ?int
    {
        $ids = $this->caso()->personal()->pluck('personal.id');
        $propio = auth()->user()?->personal_id;

        return $ids->contains($propio) ? $propio : $ids->first();
    }

    protected function ultimoSeguimiento(): ?SeguimientoCaso
    {
        return $this->caso()->seguimientos()->with('personal')
            ->orderByDesc('fecha_seguimiento')->orderByDesc('id')->first();
    }

    /** @return array<int, string> */
    protected function opcionesAbogado(): array
    {
        $nombre = fn (Personal $p) => [$p->id => $p->nombre_completo];

        // Primero los abogados del caso, luego el resto de abogados.
        return $this->caso()->personal()->get()->mapWithKeys($nombre)
            ->union(Personal::query()->where('profesion', 'Abogado')->orderBy('nombres')->get()->mapWithKeys($nombre))
            ->all();
    }

    public function form(Schema $schema): Schema
    {
        $caso = $this->caso();
        $ultimo = $this->ultimoSeguimiento();
        $instancias = EtapasProceso::instancias($caso);

        return $schema->columns(2)->components([
            DatePicker::make('fecha_seguimiento')
                ->label('Fecha')
                ->default(now()->toDateString())
                ->required()
                ->extraInputAttributes(['data-enter-nav' => 'true']),
            Select::make('personal_id')
                ->label('Abogado')
                ->options(fn () => $this->opcionesAbogado())
                ->default(fn () => $this->abogadoPorDefecto())
                ->searchable()
                ->required(),
            Select::make('etapa_proceso')
                ->label('Etapa del proceso')
                ->helperText('En qué etapa queda el caso con esta actuación.')
                ->options(fn () => EtapasProceso::opciones($caso))
                ->default($ultimo?->etapa_proceso)
                ->searchable()
                ->required(),
            Select::make('instancia')
                ->label('Instancia')
                ->helperText('Fiscalías, juzgados u otras instancias registradas abajo.')
                ->options($instancias)
                ->default($ultimo?->instancia ?? array_key_first($instancias))
                ->searchable(),
            Select::make('tipo_actuacion')
                ->label('Actuación')
                ->options(array_combine(EtapasProceso::ACTUACIONES, EtapasProceso::ACTUACIONES))
                ->required(),
            Textarea::make('observaciones')
                ->label('¿Qué se hizo?')
                ->placeholder('Ej.: Se presentó memorial de ofrecimiento de prueba; el juez fijó audiencia.')
                ->rows(3)
                ->required()
                ->columnSpanFull(),
            Section::make('¿Qué sigue?')
                ->description('Próxima actuación pendiente: aparece en el resumen y se marca en rojo si se vence.')
                ->compact()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    DatePicker::make('proxima_fecha')
                        ->label('Fecha')
                        ->extraInputAttributes(['data-enter-nav' => 'true']),
                    TextInput::make('proxima_actuacion')
                        ->label('Qué hay que hacer')
                        ->placeholder('Ej.: Audiencia de juicio oral')
                        ->requiredWith('proxima_fecha')
                        ->maxLength(255)
                        ->extraInputAttributes(['data-enter-nav' => 'true']),
                ]),
        ]);
    }

    /** Copia el nombre del abogado a "responsable" (lo usa la cronología). */
    protected static function conResponsable(array $data): array
    {
        if (filled($data['personal_id'] ?? null)) {
            $data['responsable'] = mb_substr((string) Personal::find($data['personal_id'])?->nombre_completo, 0, 100);
        }

        return $data;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('etapa_proceso')
            ->header(function () {
                $caso = $this->caso();

                return view('filament.admin.casos.seguimiento-resumen', [
                    'acciones' => array_filter($this->getTable()->getHeaderActions(), fn ($a) => $a->isVisible()),
                    'etapas' => EtapasProceso::de($caso),
                    'instancia' => EtapasProceso::instanciaVigente($caso),
                    'ultimo' => $this->ultimoSeguimiento(),
                    // La próxima actuación vigente es la del último seguimiento
                    // que la registró (las anteriores ya se reemplazaron).
                    'proximo' => $caso->seguimientos()->whereNotNull('proxima_fecha')
                        ->orderByDesc('fecha_seguimiento')->orderByDesc('id')->first(),
                ]);
            })
            ->modifyQueryUsing(fn ($query) => $query->with('personal'))
            ->columns([
                TextColumn::make('fecha_seguimiento')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('etapa_proceso')->label('Etapa')->badge()->color('primary')->wrap(),
                TextColumn::make('tipo_actuacion')->label('Actuación')->placeholder('—'),
                TextColumn::make('observaciones')->label('¿Qué se hizo?')->wrap()->limit(180)
                    ->description(fn (SeguimientoCaso $r) => $r->instancia),
                TextColumn::make('abogado_nombre')->label('Abogado')->placeholder('—'),
                TextColumn::make('proxima_fecha')->label('¿Qué sigue?')
                    ->formatStateUsing(fn (SeguimientoCaso $r) => new HtmlString(
                        '<strong>'.$r->proxima_fecha->format('d/m/Y').'</strong><br>'.e($r->proxima_actuacion)))
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->defaultSort(fn ($query) => $query->orderByDesc('fecha_seguimiento')->orderByDesc('id'))
            ->emptyStateHeading('Sin seguimientos todavía')
            ->emptyStateDescription('Registra cada actuación del abogado: qué se hizo, en qué etapa queda el caso y qué sigue.')
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar seguimiento')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Registrar seguimiento del proceso')
                    ->modalSubmitActionLabel('Guardar')
                    ->createAnother(false)
                    ->mutateDataUsing(fn (array $data) => self::conResponsable($data)),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => auth()->user()?->puede('editar'))
                    ->mutateDataUsing(fn (array $data) => self::conResponsable($data)),
                DeleteAction::make()->visible(fn () => auth()->user()?->puede('eliminar')),
            ]);
    }
}
