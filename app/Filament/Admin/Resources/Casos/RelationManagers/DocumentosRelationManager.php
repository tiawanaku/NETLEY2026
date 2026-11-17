<?php

namespace App\Filament\Admin\Resources\Casos\RelationManagers;

use App\Enums\TipoDocumento;
use App\Filament\Admin\Support\AdjuntarDocumentosClip;
use App\Models\DocumentoCliente;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Documentación del caso con el mismo formato que "Ver documentos" de
 * Personal y de Agendados: Fecha / Descripción / Documento, con Descargar,
 * Editar (solo la descripción) y Eliminar; y "Añadir documentos" con el
 * clip (varios archivos de una vez, cada uno con su descripción).
 */
class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';

    protected static ?string $title = 'Documentos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('descripcion')
            ->columns([
                TextColumn::make('fecha_origen')
                    ->label('Fecha')
                    ->state(fn (DocumentoCliente $record) => $record->fecha_origen ?? $record->created_at)
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->weight('semibold')
                    ->placeholder('—')
                    // Tipo (documentos con tipo cargado) y si lo subió el
                    // cliente desde su portal, como dato secundario.
                    ->description(fn (DocumentoCliente $record): ?string => collect([
                        $record->tipo === TipoDocumento::Fojas && filled($record->tipo_detalle)
                            ? $record->tipo->getLabel().' ('.$record->tipo_detalle.')'
                            : $record->tipo?->getLabel(),
                        $record->subido_por_cliente ? 'Subido por el cliente' : null,
                    ])->filter()->implode(' · ') ?: null)
                    ->searchable(),
                TextColumn::make('ruta')
                    ->label('Documento')
                    ->formatStateUsing(fn (?string $state) => $state ? basename($state) : '—')
                    ->color('gray')
                    ->wrap(),
                // Al final de la tabla: los mismos casilleros del formulario, solo de lectura
                // (se cambian con "Editar").
                TextColumn::make('visible_para')
                    ->label('Visible para')
                    ->state(fn (DocumentoCliente $record): string => $record->visible_cliente ? 'cliente' : 'netley')
                    // HtmlString: con ->html() Filament sanea el HTML y quita los <input>.
                    ->formatStateUsing(fn (DocumentoCliente $record): HtmlString => new HtmlString(
                        self::casilleroHtml('Netley', true)
                        .'<span style="display:inline-block;width:1.25rem;"></span>'
                        .self::casilleroHtml('Cliente', (bool) $record->visible_cliente)
                    )),
            ])
            ->defaultSort('id')
            ->headerActions([
                $this->anadirDocumentosAction(),
            ])
            ->modifyUngroupedRecordActionsUsing(fn ($action) => $action->button())
            ->recordActions([
                Action::make('descargar')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->size('sm')
                    ->action(function (DocumentoCliente $record) {
                        $disk = Storage::disk(config('filament.default_filesystem_disk'));

                        if (blank($record->ruta) || ! $disk->exists($record->ruta)) {
                            Notification::make()->title('No se encontró el archivo')->danger()->send();

                            return null;
                        }

                        return response()->streamDownload(
                            fn () => print ($disk->get($record->ruta)),
                            basename($record->ruta)
                        );
                    }),
                Action::make('editarDocumento')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->size('sm')
                    ->visible(fn () => auth()->user()?->puede('editar'))
                    ->modalHeading('Editar documento')
                    ->modalWidth('3xl')
                    ->modalSubmitActionLabel('Guardar')
                    ->modalCancelActionLabel('Cancelar')
                    ->fillForm(fn (DocumentoCliente $record): array => ['descripcion' => $record->descripcion, 'visible_cliente' => $record->visible_cliente])
                    ->schema([
                        // Descripción y, a su lado, "Visible para: Netley / Cliente".
                        Grid::make(2)
                            ->extraAttributes(['class' => 'netley-editar-documento-fila'])
                            ->schema([
                                TextInput::make('descripcion')
                                    ->label('Descripción')
                                    ->required()
                                    ->maxLength(100)
                                    ->extraInputAttributes(['data-enter-nav' => 'true']),
                                Group::make(self::casillerosVisibilidad())->columns(['default' => 3])->extraAttributes(['class' => 'netley-documento-visibilidad']),
                            ]),
                    ])
                    ->action(function (DocumentoCliente $record, array $data): void {
                        $record->update(['descripcion' => $data['descripcion'], 'visible_cliente' => (bool) ($data['visible_cliente'] ?? false)]);

                        Notification::make()->title('Documento actualizado')->success()->send();
                    }),
                Action::make('eliminarDocumento')
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->size('sm')
                    ->visible(fn () => auth()->user()?->puede('eliminar'))
                    ->requiresConfirmation()
                    ->modalHeading('¿Seguro que desea eliminar el documento:')
                    ->modalDescription(fn (DocumentoCliente $record): HtmlString => new HtmlString(
                        '<strong style="font-size:1rem;">'.e($record->descripcion ?: basename((string) $record->ruta)).'</strong>?'
                    ))
                    ->modalSubmitActionLabel('Eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->modalFooterActionsAlignment(Alignment::Start)
                    ->extraModalWindowAttributes(['class' => 'netley-eliminar-personal'])
                    ->action(function (DocumentoCliente $record): void {
                        if (filled($record->ruta)) {
                            Storage::disk(config('filament.default_filesystem_disk'))->delete($record->ruta);
                        }

                        $record->delete();

                        Notification::make()->title('Documento eliminado')->success()->send();
                    }),
            ]);
    }

    /**
     * "Visible para": Netley siempre (marcado y fijo); Cliente es opcional —
     * si se marca, el documento aparece en el portal del cliente.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected static function casillerosVisibilidad(): array
    {
        // En una sola línea: "Visible para:  [x] Netley  [ ] Cliente".
        return [
            Placeholder::make('visible_para')
                ->hiddenLabel()
                ->content(new HtmlString('<span style="font-weight:500;">Visible para:</span>')),
            Placeholder::make('visible_netley')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<label style="display:inline-flex;align-items:center;gap:8px;opacity:.75;cursor:not-allowed;">'
                    .'<input type="checkbox" checked disabled style="width:16px;height:16px;"> Netley</label>'
                )),
            Checkbox::make('visible_cliente')
                ->label('Cliente')
                ->inlineLabel(false),
        ];
    }

    /** Casillero de solo lectura (columna "Visible para"). */
    protected static function casilleroHtml(string $etiqueta, bool $marcado): string
    {
        return '<label style="display:inline-flex;align-items:center;gap:6px;">'
            .'<input type="checkbox" disabled'.($marcado ? ' checked' : '').' style="width:16px;height:16px;"> '.e($etiqueta).'</label>';
    }

    /** Mismo formulario de adjuntar que Agendar / Personal: clip + descripción por archivo. */
    protected function anadirDocumentosAction(): Action
    {
        return Action::make('anadirDocumentos')
            ->label('Añadir documentos')
            ->icon('heroicon-o-paper-clip')
            ->modalHeading('Añadir documentos')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Guardar')
            ->modalCancelActionLabel('Cancelar')
            ->schema([
                Repeater::make('documentos')
                    ->label('Adjuntar documentos')
                    // netley-fila-documento-ancha: la descripción llega hasta
                    // el borde y los casilleros van debajo (admin-theme.css).
                    ->extraAttributes(['class' => 'netley-fila-documento netley-fila-documento-ancha'])
                    ->schema([
                        // El archivo llega desde el clip (ver
                        // AdjuntarDocumentosClip); oculto, se identifica con
                        // el nombre de al lado.
                        FileUpload::make('ruta')
                            ->hiddenLabel()
                            ->directory('documentos-clientes')
                            ->extraAttributes(['class' => 'netley-documento-oculto'])
                            ->required(),
                        Placeholder::make('nombreArchivo')
                            ->label('Archivo')
                            ->content(fn (Get $get) => AdjuntarDocumentosClip::nombreArchivo($get('ruta')) ?? '—'),
                        TextInput::make('descripcion')
                            ->label('Descripción')
                            ->maxLength(100),
                        Group::make(self::casillerosVisibilidad())
                            ->columns(['default' => 3])
                            ->extraAttributes(['class' => 'netley-documento-visibilidad']),
                    ])
                    ->columns(2)
                    ->addable(false)
                    ->reorderable(false)
                    ->defaultItems(0)
                    ->minItems(1)
                    ->validationMessages(['min' => 'Adjunta al menos un documento con el clip.'])
                    ->columnSpanFull(),
                AdjuntarDocumentosClip::make('documentos', 'documentos-clientes'),
            ])
            ->action(function (array $data): void {
                $caso = $this->getOwnerRecord();

                foreach ($data['documentos'] ?? [] as $documento) {
                    if (blank($documento['ruta'] ?? null)) {
                        continue;
                    }

                    $caso->documentos()->create([
                        'cliente_id' => $caso->cliente_id,
                        'ruta' => $documento['ruta'],
                        'descripcion' => $documento['descripcion'] ?? null,
                        'visible_cliente' => (bool) ($documento['visible_cliente'] ?? false),
                        'fecha_origen' => now(),
                    ]);
                }

                Notification::make()->title('Documentos agregados')->success()->send();
            });
    }
}
