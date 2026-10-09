<?php

namespace App\Filament\Admin\Pages;

use App\Enums\EstadoCaso;
use App\Enums\TipoDocumento;
use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Models\Caso;
use App\Models\DocumentoCliente;
use App\Models\SeguimientoCaso;
use App\Support\AlcancePersonal;
use Barryvdh\DomPDF\Facade\Pdf;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Checklist diario de casos vigentes (scoped igual que el resto del panel,
 * ver AlcancePersonal) con un botón para redactar el informe del día: por
 * cada caso marcado, escribe una nota que queda registrada como Seguimiento
 * del caso — la misma información que ya ve el staff en la pestaña
 * "Seguimiento del proceso" y el cliente en su portal. Master/Administrador
 * ven todos los casos sin restricción (AlcancePersonal), así esta misma
 * vista les sirve para supervisar si cada caso ya tiene o no seguimiento
 * registrado hoy y quién lo hizo.
 */
class SeguimientoDiario extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Seguimiento diario';

    protected static \UnitEnum|string|null $navigationGroup = 'Cliente Ejecutivo';

    protected static ?int $navigationSort = 35;

    protected static ?string $slug = 'seguimiento-diario';

    protected string $view = 'filament.admin.pages.seguimiento-diario';

    protected const ETAPA = 'Seguimiento diario';

    /** @var array<int, int> */
    public array $seleccionados = [];

    public ?int $casoIndividualId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->puede('seguimiento_diario') ?? false;
    }

    public function getTitle(): string
    {
        return 'Seguimiento diario';
    }

    /**
     * @return Collection<int, Caso>
     */
    public function getCasosProperty(): Collection
    {
        $ids = AlcancePersonal::idsRelevantes(auth()->user());

        return Caso::query()
            ->where('estado', '!=', EstadoCaso::Cerrado)
            ->when($ids !== null, fn ($q) => $q->whereHas('personal', fn ($q2) => $q2->whereIn('personal.id', $ids)))
            ->with('cliente')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * El último seguimiento de hoy por caso (de cualquier responsable), para
     * mostrar en la tabla si ya se hizo o no seguimiento y quién lo hizo —
     * lo que necesita ver Master/Administrador para supervisar el día.
     *
     * @return Collection<int, SeguimientoCaso>
     */
    public function getSeguimientosHoyProperty(): Collection
    {
        return SeguimientoCaso::query()
            ->where('etapa_proceso', self::ETAPA)
            ->whereIn('caso_id', $this->casos->pluck('id'))
            ->whereDate('fecha_seguimiento', now()->toDateString())
            ->orderByDesc('fecha_seguimiento')
            ->get()
            ->unique('caso_id')
            ->keyBy('caso_id');
    }

    public function tieneSeguimientoHoy(int $casoId): bool
    {
        return $this->seguimientosHoy->has($casoId);
    }

    public function responsableHoyDe(int $casoId): ?string
    {
        return $this->seguimientosHoy->get($casoId)?->responsable;
    }

    public function tieneObservacionesHoy(int $casoId): bool
    {
        return filled($this->seguimientosHoy->get($casoId)?->observaciones);
    }

    /** El propio seguimiento de hoy del usuario actual para un caso (para prellenar los formularios). */
    protected function miSeguimientoHoyDe(int $casoId): ?SeguimientoCaso
    {
        $responsable = auth()->user()?->personal?->nombre_completo;

        return SeguimientoCaso::query()
            ->where('caso_id', $casoId)
            ->where('etapa_proceso', self::ETAPA)
            ->where('responsable', $responsable)
            ->whereDate('fecha_seguimiento', now()->toDateString())
            ->first();
    }

    protected function guardarSeguimiento(int $casoId, ?string $observaciones): void
    {
        $existente = $this->miSeguimientoHoyDe($casoId);

        if ($existente) {
            $existente->update(['observaciones' => $observaciones, 'fecha_seguimiento' => now()]);

            return;
        }

        SeguimientoCaso::create([
            'caso_id' => $casoId,
            'fecha_seguimiento' => now(),
            'responsable' => auth()->user()?->personal?->nombre_completo,
            'etapa_proceso' => self::ETAPA,
            'observaciones' => $observaciones,
        ]);
    }

    public function marcarTodos(): void
    {
        $this->seleccionados = $this->casos->pluck('id')->all();
    }

    public function desmarcarTodos(): void
    {
        $this->seleccionados = [];
    }

    /** Suma de fojas (tipo Fojas) registradas en los documentos del expediente de un caso. */
    public function fojasDe(int $casoId): int
    {
        return (int) DocumentoCliente::query()
            ->where('caso_id', $casoId)
            ->where('tipo', TipoDocumento::Fojas)
            ->sum('tipo_detalle');
    }

    /** La pestaña "Documentos" es la 3ra (índice 2) en CasoResource::getRelations(). */
    public function urlDocumentos(int $casoId): string
    {
        return CasoResource::getUrl('edit', ['record' => $casoId]).'?relation=2';
    }

    /** Abre la ventana de "Informe del caso" para un caso puntual. */
    public function abrirInformeCaso(int $casoId): void
    {
        $this->casoIndividualId = $casoId;
        $this->mountAction('informeCaso');
    }

    /**
     * Ventana para registrar (o editar) el seguimiento de hoy de un solo
     * caso, disparada por el botón "Informe del caso" de cada fila.
     */
    public function informeCasoAction(): Action
    {
        return Action::make('informeCaso')
            ->modalHeading(function () {
                $caso = Caso::find($this->casoIndividualId);

                return 'Seguimiento de hoy — Caso #'.$this->casoIndividualId.' — '.($caso?->cliente?->nombre_completo ?? 'Sin cliente');
            })
            ->modalSubmitActionLabel('Guardar seguimiento')
            ->fillForm(fn () => [
                'observaciones' => $this->miSeguimientoHoyDe($this->casoIndividualId)?->observaciones,
            ])
            ->schema([
                Textarea::make('observaciones')
                    ->label('¿Qué se hizo hoy en este caso?')
                    ->rows(4)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->guardarSeguimiento($this->casoIndividualId, $data['observaciones']);

                Notification::make()->title('Seguimiento del caso guardado')->success()->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imprimirInformeDiario')
                ->label('Imprimir informe diario')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(fn () => $this->imprimirInformeDiario()),
            Action::make('redactarInforme')
                ->label('Redactar informe diario')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->disabled(fn () => empty($this->seleccionados))
                ->modalHeading('Informe diario de seguimiento')
                ->modalDescription('Detalle de los casos marcados: si ya tienen seguimiento hoy, se muestra su nota para revisarla o editarla.')
                ->modalSubmitActionLabel('Guardar informe')
                ->schema(fn () => collect($this->seleccionados)
                    ->map(function (int $id) {
                        $caso = Caso::find($id);
                        $yaTiene = $this->tieneSeguimientoHoy($id);

                        return Textarea::make('nota_'.$id)
                            ->label(
                                'Caso #'.$id.' — '.($caso?->cliente?->nombre_completo ?? 'Sin cliente')
                                .($yaTiene ? ' · Ya tiene seguimiento hoy ('.$this->responsableHoyDe($id).')' : ' · Sin seguimiento hoy')
                            )
                            ->rows(2)
                            ->required();
                    })
                    ->values()
                    ->all())
                ->fillForm(fn () => collect($this->seleccionados)
                    ->mapWithKeys(fn (int $id) => ['nota_'.$id => $this->miSeguimientoHoyDe($id)?->observaciones])
                    ->all())
                ->action(function (array $data): void {
                    foreach ($this->seleccionados as $id) {
                        $this->guardarSeguimiento($id, $data['nota_'.$id] ?? null);
                    }

                    $this->seleccionados = [];

                    Notification::make()->title('Informe diario registrado')->success()->send();
                }),
        ];
    }

    /**
     * PDF del informe diario: detalle de qué casos se siguieron hoy y si
     * hubo o no observaciones — los seguimientos de tipo "Seguimiento
     * diario" registrados hoy por el usuario actual, uno por caso.
     */
    protected function imprimirInformeDiario(): mixed
    {
        $responsable = auth()->user()?->personal?->nombre_completo;

        $seguimientos = SeguimientoCaso::query()
            ->where('etapa_proceso', self::ETAPA)
            ->where('responsable', $responsable)
            ->whereDate('fecha_seguimiento', now()->toDateString())
            ->with('caso.cliente')
            ->orderBy('caso_id')
            ->get();

        return response()->streamDownload(
            fn () => print(Pdf::loadView('pdf.informe-diario', [
                'responsable' => $responsable,
                'fecha' => now(),
                'seguimientos' => $seguimientos,
            ])->output()),
            'informe-diario-'.now()->toDateString().'.pdf'
        );
    }
}
