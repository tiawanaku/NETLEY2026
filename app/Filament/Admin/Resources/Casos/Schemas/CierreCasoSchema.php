<?php

namespace App\Filament\Admin\Resources\Casos\Schemas;

use App\Enums\EstadoPersonal;
use App\Filament\Admin\Support\AdjuntarDocumentosClip;
use App\Models\Caso;
use App\Models\Personal;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Formulario del informe de cierre de caso (reemplaza informe_cierre_caso.php).
 * Se usa como Action::schema() sobre CasoResource: al enviarse crea el informe
 * y transiciona el caso a "cerrado" (ver CasosTable::configure -> acción cerrarCaso).
 */
class CierreCasoSchema
{
    /**
     * Personal habilitado (todas las profesiones), por nombre completo.
     *
     * @return array<string, string>
     */
    protected static function opcionesPersonal(): array
    {
        // Primero las instituciones, luego el personal.
        return [
            'Hand i Hand' => 'Hand i Hand',
            'Netley' => 'Netley',
        ] + Personal::query()
            ->where('estado', EstadoPersonal::Habilitado)
            ->orderBy('nombres')
            ->get()
            ->mapWithKeys(fn (Personal $personal) => [
                $personal->nombre_completo => $personal->nombre_completo.(filled($personal->profesion) ? ' — '.$personal->profesion : ''),
            ])
            ->all();
    }

    /** Resultados en que la pérdida es el saldo y se muestra el patrocinio HiH. */
    public const CON_PERDIDA = ['se_perdio_proceso', 'transaccion', 'desistimiento', 'abandono'];

    /**
     * Pérdida en perdido / acuerdo / desistimiento / abandono: el saldo
     * pendiente (Netley) más el patrocinio Hand i Hand del caso (HiH).
     */
    public static function perdida(mixed $saldo, ?Caso $caso): float
    {
        return round(self::perdidaNetley($saldo, $caso) + self::perdidaHih($caso, $saldo), 2);
    }

    /**
     * Parte de la pérdida de Hand i Hand: el patrocinio del caso. En costas y
     * costos el saldo ya incluye el patrocinio: es la parte de HiH que queda
     * dentro del saldo pendiente.
     */
    public static function perdidaHih(?Caso $caso, mixed $saldo = null): float
    {
        $hih = $caso?->patrocinio_hih ? round((float) $caso->monto_patrocinio, 2) : 0.0;

        return $caso?->esCostas() ? min($hih, max((float) ($saldo ?? $caso->saldo), 0.0)) : $hih;
    }

    /** ¿El caso tiene patrocinio Hand i Hand (mayor a 0)? */
    public static function tienePatrocinio(?Caso $caso): bool
    {
        return (bool) $caso?->patrocinio_hih && (float) $caso->monto_patrocinio > 0;
    }

    /** Parte de la pérdida de Netley: el saldo pendiente (en costas, sin la parte de HiH). */
    public static function perdidaNetley(mixed $saldo, ?Caso $caso): float
    {
        $saldo = max((float) $saldo, 0.0);

        return round($caso?->esCostas() ? $saldo - self::perdidaHih($caso, $saldo) : $saldo, 2);
    }

    /**
     * Opciones de responsable (¿Quién asume? y la tabla por organización):
     * Hand i Hand, Netley, personal habilitado y el abogado responsable
     * elegido; con Abandono, también el cliente.
     *
     * @return array<string, string>
     */
    protected static function opcionesResponsable(Get $get, ?Caso $record): array
    {
        return ($get('resultado') === 'abandono'
                ? ['Cliente' => 'Cliente'.($record?->cliente ? ' — '.$record->cliente->nombre_completo : '')]
                : [])
            + self::opcionesPersonal()
            + (filled($get('abogado_responsable')) ? [$get('abogado_responsable') => $get('abogado_responsable')] : []);
    }

    /** Primer abogado asignado al caso (responsable por defecto). */
    protected static function abogadoDelCaso(?Caso $record): ?string
    {
        return $record?->personal()->where('profesion', 'Abogado')->first()?->nombre_completo;
    }

    protected static function bs(float $monto): string
    {
        return 'Bs. '.number_format($monto, fmod($monto, 1.0) == 0.0 ? 0 : 2, '.', '');
    }

    /** ¿Este cierre sigue como costas y costos? Ganado y con patrocinio HiH mayor a 0. */
    public static function vaACostas(?string $resultado, ?Caso $caso): bool
    {
        return $resultado === 'se_gano_proceso'
            && $caso?->patrocinio_hih
            && (float) $caso->monto_patrocinio > 0;
    }

    public static function make(): array
    {
        // Al abrir solo se ve hasta Resultado; el resto aparece según lo elegido.
        $conResultado = fn (Get $get): bool => filled($get('resultado'));

        return [
            // Orden pedido: fecha de cierre, abogado responsable, resultado.
            DatePicker::make('fecha_cierre')->label('Fecha de cierre')->default(now())->required(),
            // Abogados habilitados (más los del caso, aunque hoy no lo
            // estén); llega elegido el primer abogado asignado al caso.
            Select::make('abogado_responsable')
                ->label('Abogado responsable')
                ->options(fn (?Caso $record): array => Personal::query()
                    ->where('profesion', 'Abogado')
                    ->where(fn ($q) => $q->where('estado', EstadoPersonal::Habilitado)
                        ->when($record, fn ($q) => $q->orWhereIn('id', $record->personal()->pluck('personal.id'))))
                    ->orderBy('nombres')
                    ->get()
                    ->mapWithKeys(fn (Personal $personal) => [$personal->nombre_completo => $personal->nombre_completo])
                    ->all())
                ->default(fn (?Caso $record): ?string => $record?->personal()->where('profesion', 'Abogado')->first()?->nombre_completo)
                ->live()
                // "¿Quién asume?" sigue al abogado mientras no se haya cambiado a mano.
                ->afterStateUpdated(function ($state, $old, Get $get, $set): void {
                    if (blank($get('asume')) || $get('asume') === $old) {
                        $set('asume', $state);
                    }
                })
                ->searchable()
                ->native(false)
                ->required(),
            Select::make('resultado')
                ->options(\App\Models\InformeCierreCaso::RESULTADOS)
                ->live()
                ->afterStateUpdated(function ($state, Get $get, $set): void {
                    // Por defecto asume el abogado responsable ("Cliente" solo vale con Abandono).
                    if (blank($get('asume')) || ($get('asume') === 'Cliente' && $state !== 'abandono')) {
                        $set('asume', $get('abogado_responsable'));
                    }
                })
                ->required(),
            // Siempre: el mismo "Resumen" del paso 3 (iguala, patrocinio,
            // anticipo y saldo, sin el plan de cuotas), con lo guardado en el caso.
            Placeholder::make('resumenPagos')
                ->label('Resumen de pagos')
                ->inlineLabel(false)
                ->columnSpanFull()
                ->content(fn (?Caso $record) => $record
                    ? \App\Filament\Admin\Support\ClienteCasoWizard::resumenPlanHtml(\App\Filament\Admin\Support\ClienteCasoWizard::planDeCaso($record), ' (anticipo)')
                    : '—'),
            // Saldo pendiente al cierre: el saldo actual del caso, sin
            // mostrarse (se guarda en el informe; es la pérdida de Netley).
            Hidden::make('saldo')->default(fn (?Caso $record) => (float) ($record?->saldo ?? 0)),
            // Con pérdida: el total (HiH + Netley) y, debajo, por organización.
            Placeholder::make('totalPerdida')
                ->label('Total de la pérdida')
                ->visible(fn (Get $get): bool => in_array($get('resultado'), self::CON_PERDIDA, true))
                ->content(fn (Get $get, ?Caso $record): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                    '<strong style="font-size:1.05rem;">'.e(self::bs(self::perdida($get('saldo'), $record))).'</strong>'
                )),
            // Parte | Monto pérdida | Responsable. HiH = patrocinio;
            // Netley = saldo pendiente.
            Grid::make(3)
                ->visible(fn (Get $get): bool => in_array($get('resultado'), self::CON_PERDIDA, true))
                ->columnSpanFull()
                ->extraAttributes(['class' => 'netley-perdida-organizaciones'])
                ->schema([
                    // Fila de HiH solo si el caso tiene patrocinio Hand i Hand;
                    // sin ella, los títulos de las columnas pasan a la fila de Netley.
                    Placeholder::make('org_hih')->label('Parte')->inlineLabel(false)->content('HiH')
                        ->visible(fn (?Caso $record): bool => self::tienePatrocinio($record)),
                    Placeholder::make('monto_hih')->label('Monto pérdida')->inlineLabel(false)
                        ->visible(fn (?Caso $record): bool => self::tienePatrocinio($record))
                        ->content(fn (Get $get, ?Caso $record): string => self::bs(self::perdidaHih($record, $get('saldo')))),
                    Select::make('responsable_hih')->label('Responsable')->inlineLabel(false)
                        ->visible(fn (?Caso $record): bool => self::tienePatrocinio($record))
                        ->options(fn (Get $get, ?Caso $record): array => self::opcionesResponsable($get, $record))
                        ->default(fn (?Caso $record): ?string => self::abogadoDelCaso($record))
                        ->searchable()
                        ->native(false),
                    Placeholder::make('org_netley')->label('Parte')->inlineLabel(false)
                        ->hiddenLabel(fn (?Caso $record): bool => self::tienePatrocinio($record))->content('Netley'),
                    Placeholder::make('monto_netley')->label('Monto pérdida')->inlineLabel(false)
                        ->hiddenLabel(fn (?Caso $record): bool => self::tienePatrocinio($record))
                        ->content(fn (Get $get, ?Caso $record): string => self::bs(self::perdidaNetley($get('saldo'), $record))),
                    Select::make('responsable_netley')->label('Responsable')->inlineLabel(false)
                        ->hiddenLabel(fn (?Caso $record): bool => self::tienePatrocinio($record))
                        ->options(fn (Get $get, ?Caso $record): array => self::opcionesResponsable($get, $record))
                        ->default(fn (?Caso $record): ?string => self::abogadoDelCaso($record))
                        ->searchable()
                        ->native(false),
                ]),
            // Hand i Hand, Netley o personal registrado; se guarda el nombre.
            Select::make('asume')
                ->label(fn (Get $get, ?Caso $record): string => self::vaACostas($get('resultado'), $record)
                    ? '¿Quién se hace responsable del saldo?'
                    : '¿Quién asume el saldo/pérdida?')
                ->options(fn (Get $get, ?Caso $record): array => self::opcionesResponsable($get, $record))
                ->default(fn (?Caso $record): ?string => self::abogadoDelCaso($record))
                ->required(fn (Get $get, ?Caso $record): bool => self::vaACostas($get('resultado'), $record))
                // Con pérdida lo reemplaza el responsable por organización (tabla de arriba).
                ->visible(fn (Get $get): bool => filled($get('resultado')) && ! in_array($get('resultado'), self::CON_PERDIDA, true))
                ->searchable()
                ->native(false),
            Textarea::make('nota_netley')->label('Nota interna')->visible($conResultado)->columnSpanFull(),
            // Al final, documentos del cierre con el mismo formato de todo el
            // sistema: el clip agrega filas (archivo + descripción). Se
            // guardan en la pestaña Documentos del caso (ver
            // CasoActions::cerrar()).
            Repeater::make('documentos')
                ->label('Documentos del cierre')
                ->extraAttributes(['class' => 'netley-fila-documento'])
                ->schema([
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
                ])
                ->columns(2)
                ->addable(false)
                ->reorderable(false)
                ->defaultItems(0)
                ->visible($conResultado)
                ->columnSpanFull(),
            AdjuntarDocumentosClip::make('documentos', 'documentos-clientes')->visible($conResultado),
            // Lo último. Ganado con patrocinio HiH: marcado, al guardar abre
            // el asistente de 4 pasos del proceso de costas y costos
            // (CasoActions::cerrar()); si no, se inicia luego desde la lista
            // de casos del cliente ("Iniciar costas y costos").
            Checkbox::make('iniciar_costas')
                ->label('Iniciar proceso de costas y costos')
                ->default(false)
                ->visible(fn (Get $get, ?Caso $record): bool => self::vaACostas($get('resultado'), $record))
                ->columnSpanFull(),
        ];
    }
}
