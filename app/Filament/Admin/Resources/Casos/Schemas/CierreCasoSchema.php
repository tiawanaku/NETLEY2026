<?php

namespace App\Filament\Admin\Resources\Casos\Schemas;

use App\Enums\EstadoPersonal;
use App\Filament\Admin\Support\AdjuntarDocumentosClip;
use App\Models\Caso;
use App\Models\Personal;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                    // Con pérdida: la pérdida es el saldo.
                    if (in_array($state, self::CON_PERDIDA, true)) {
                        $set('perdida', $get('saldo'));
                    }

                    // Por defecto asume el abogado responsable ("Cliente" solo vale con Abandono).
                    if (blank($get('asume')) || ($get('asume') === 'Cliente' && $state !== 'abandono')) {
                        $set('asume', $get('abogado_responsable'));
                    }
                })
                ->required(),
            // Con pérdida: monto del patrocinio Hand i Hand del caso.
            Placeholder::make('patrocinioHih')
                ->label('Patrocinio Hand i Hand')
                ->visible(fn (Get $get): bool => in_array($get('resultado'), self::CON_PERDIDA, true))
                ->content(fn (?Caso $record): string => $record?->patrocinio_hih
                    ? 'Bs. '.number_format((float) $record->monto_patrocinio, 2, '.', '')
                        .((float) $record->porcentaje_patrocinio > 0 ? ' ('.rtrim(rtrim(number_format((float) $record->porcentaje_patrocinio, 2, '.', ''), '0'), '.').'%)' : '')
                    : 'Sin patrocinio'),
            // Llega con el saldo actual del caso.
            TextInput::make('saldo')->numeric()->prefix('Bs.')
                ->default(fn (?Caso $record) => (float) ($record?->saldo ?? 0))
                ->label('Saldo pendiente al cierre')
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Get $get, $set) => in_array($get('resultado'), self::CON_PERDIDA, true)
                    ? $set('perdida', $state)
                    : null)
                ->visible($conResultado),
            // Si se ganó no hay pérdida. Con pérdida, la pérdida es el saldo
            // (no se escribe; se vuelve a fijar al guardar, CasoActions::cerrar()).
            TextInput::make('perdida')->numeric()->prefix('Bs.')->default(0)
                ->label(fn (Get $get): string => in_array($get('resultado'), self::CON_PERDIDA, true) ? 'Pérdida' : 'Pérdida (si aplica)')
                ->readOnly(fn (Get $get): bool => in_array($get('resultado'), self::CON_PERDIDA, true))
                ->visible(fn (Get $get): bool => filled($get('resultado')) && $get('resultado') !== 'se_gano_proceso'),
            // Hand i Hand, Netley o personal registrado; se guarda el nombre.
            Select::make('asume')
                ->label(fn (Get $get, ?Caso $record): string => self::vaACostas($get('resultado'), $record)
                    ? '¿Quién se hace responsable del saldo?'
                    : '¿Quién asume el saldo/pérdida?')
                // Incluye al abogado responsable aunque hoy no esté habilitado.
                // Con Abandono también puede asumirlo el cliente.
                ->options(fn (Get $get, ?Caso $record): array => ($get('resultado') === 'abandono'
                        ? ['Cliente' => 'Cliente'.($record?->cliente ? ' — '.$record->cliente->nombre_completo : '')]
                        : [])
                    + self::opcionesPersonal()
                    + (filled($get('abogado_responsable')) ? [$get('abogado_responsable') => $get('abogado_responsable')] : []))
                ->default(fn (?Caso $record): ?string => $record?->personal()->where('profesion', 'Abogado')->first()?->nombre_completo)
                ->required(fn (Get $get, ?Caso $record): bool => self::vaACostas($get('resultado'), $record))
                ->visible($conResultado)
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
