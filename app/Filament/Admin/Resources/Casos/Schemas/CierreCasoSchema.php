<?php

namespace App\Filament\Admin\Resources\Casos\Schemas;

use App\Enums\EstadoPersonal;
use App\Filament\Admin\Support\AdjuntarDocumentosClip;
use App\Models\Caso;
use App\Models\Personal;
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
    protected static array $resultados = [
        'se_gano_proceso' => 'Se ganó el proceso',
        'se_perdio_proceso' => 'Se perdió el proceso',
        'transaccion' => 'Transacción / acuerdo',
        'desistimiento' => 'Desistimiento',
        'archivo' => 'Archivo de obrados',
        'otro' => 'Otro',
    ];

    /**
     * Personal habilitado (todas las profesiones), por nombre completo.
     *
     * @return array<string, string>
     */
    protected static function opcionesPersonal(): array
    {
        return Personal::query()
            ->where('estado', EstadoPersonal::Habilitado)
            ->orderBy('nombres')
            ->get()
            ->mapWithKeys(fn (Personal $personal) => [
                $personal->nombre_completo => $personal->nombre_completo.(filled($personal->profesion) ? ' — '.$personal->profesion : ''),
            ])
            ->all();
    }

    public static function make(): array
    {
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
                ->searchable()
                ->native(false)
                ->required(),
            Select::make('resultado')
                ->options(self::$resultados)
                ->required(),
            TextInput::make('saldo')->numeric()->prefix('Bs.')->default(0)->label('Saldo pendiente al cierre'),
            TextInput::make('perdida')->numeric()->prefix('Bs.')->default(0)->label('Pérdida (si aplica)'),
            // Personal registrado (habilitado); se guarda el nombre.
            Select::make('asume')
                ->label('¿Quién asume el saldo/pérdida?')
                ->options(fn (): array => self::opcionesPersonal())
                ->searchable()
                ->native(false),
            Select::make('seguimiento_responsable')
                ->label('Responsable de seguimiento posterior')
                ->options(fn (): array => self::opcionesPersonal())
                ->searchable()
                ->native(false),
            Textarea::make('opciones')->label('Opción de cierre')->columnSpanFull(),
            Textarea::make('nota_netley')->label('Nota interna')->columnSpanFull(),
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
                ->columnSpanFull(),
            AdjuntarDocumentosClip::make('documentos', 'documentos-clientes'),
        ];
    }
}
