<?php

namespace App\Filament\Admin\Resources\Casos\Schemas;

use App\Enums\EstadoCaso;
use App\Filament\Admin\Forms\Components\DelitoSelect;
use App\Filament\Admin\Support\CamposDenuncia;
use App\Filament\Admin\Support\CamposServicios;
use App\Filament\Admin\Support\ClienteCasoWizard;
use App\Models\Caso;
use App\Support\Telefono;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class CasoForm
{
    public static function configure(Schema $schema): Schema
    {
        // Igual que en Consultas/Clientes: la ficha del caso queda de solo
        // lectura una vez creada — los cambios de estado se hacen con las
        // acciones dedicadas (Cerrar/Reactivar caso, ver CasoActions), no
        // editando estos campos directamente. Las pestañas de seguimiento,
        // documentación, pagos, fiscalías y juzgados siguen funcionando
        // normal, son RelationManagers aparte.
        // Con "Editar" en la ficha (EditCaso::$editando) se habilitan.
        $isEditing = fn (string $operation, $livewire): bool => $operation === 'edit' && ! ($livewire->editando ?? false);

        return $schema
            ->inlineLabel()
            ->components([
                Section::make('Datos del cliente')
                    ->schema([
                        TextInput::make('cliente_nombre')
                            ->label('Nombre completo')
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?Caso $record) => $record?->cliente?->nombre_completo)
                            ->disabled(),
                        TextInput::make('cliente_ci')
                            ->label('Carnet de identidad')
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?Caso $record) => $record?->cliente
                                ? trim($record->cliente->ci.($record->cliente->extension ? " ({$record->cliente->extension})" : ''))
                                : null)
                            ->disabled(),
                        TextInput::make('cliente_telefono')
                            ->label('Teléfono')
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?Caso $record) => Telefono::formatear($record?->cliente?->telefono))
                            ->disabled(),
                        TextInput::make('cliente_whatsapp')
                            ->label('WhatsApp')
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?Caso $record) => Telefono::formatear($record?->cliente?->whatsapp))
                            ->disabled(),
                        TextInput::make('cliente_correo')
                            ->label('Correo')
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?Caso $record) => $record?->cliente?->correo)
                            ->disabled(),
                        TextInput::make('cliente_direccion')
                            ->label('Dirección')
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?Caso $record) => $record?->cliente?->direccion)
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (?Caso $record) => $record?->cliente !== null),

                // Caso eliminado: fecha y justificación, resaltadas (como
                // "Personal retirado").
                Section::make('Caso eliminado')
                    ->icon('heroicon-o-trash')
                    ->iconColor('danger')
                    ->visible(fn (?Caso $record) => (bool) $record?->trashed())
                    ->extraAttributes(['style' => 'border:2px solid rgb(220 38 38);background:rgba(220,38,38,.07);'])
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Placeholder::make('eliminacionFecha')
                            ->label('Fecha')
                            ->content(fn (?Caso $record) => new HtmlString('<strong>'.e(($record?->fecha_eliminacion ?? $record?->deleted_at)?->format('d/m/Y') ?? '—').'</strong>')),
                        Placeholder::make('eliminacionMotivo')
                            ->label('Motivo')
                            ->content(fn (?Caso $record) => new HtmlString('<strong>'.e($record?->motivo_eliminacion ?: '—').'</strong>')),
                        Placeholder::make('eliminacionDocumento')
                            ->label('Documento')
                            ->content(fn (?Caso $record) => \App\Filament\Admin\Support\Eliminacion::enlaceDocumento($record?->documento_eliminacion)),
                    ]),

                // Mismos campos, orden y disposición que el paso 2
                // ("Proceso") del formulario de Cliente ejecutivo
                // (ClienteCasoWizard::camposProceso()).
                Section::make('Datos del proceso')
                    ->schema([
                        // Solo al crear un caso suelto; en la ficha el cliente
                        // ya se ve arriba, en "Datos del cliente".
                        Select::make('cliente_id')
                            ->label('Cliente')
                            ->relationship('cliente', 'nombres')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nombre_completo.' — CI '.$record->ci)
                            ->searchable(['nombres', 'ap_paterno', 'ci'])
                            ->preload()
                            ->required()
                            ->visibleOn('create')
                            ->columnSpanFull(),
                        ...array_map(fn ($field) => $field->disabled($isEditing), DelitoSelect::make(delitoLabel: 'Tipo de proceso')),
                        // Casos creados con materia / delito "Otros" (escritos a mano).
                        TextInput::make('materia_texto')
                            ->label('Materia legal (especificar)')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?string $state) => filled($state)),
                        TextInput::make('delito_texto')
                            ->label('Tipo de proceso (especificar)')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?string $state) => filled($state)),
                        // Apersonamiento a la izquierda y "Servicios
                        // requeridos" al lado, como en el paso 2. Un valor
                        // fuera de la lista (casos antiguos, u "Otros"
                        // especificado a mano) se agrega para que se vea.
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Group::make([
                                    Select::make('apersonamiento')
                                        ->options(fn (?string $state) => collect(ClienteCasoWizard::APERSONAMIENTOS)
                                            ->when(filled($state), fn ($opciones) => $opciones->push($state))
                                            ->unique()
                                            ->mapWithKeys(fn ($v) => [$v => $v])
                                            ->all())
                                        ->native(false)
                                        ->disabled($isEditing),
                                ]),
                                CamposServicios::campo(disabled: $isEditing),
                            ])
                            ->columnSpanFull(),
                        Textarea::make('descripcion')->label('Nota')->columnSpanFull()->inlineLabel(false)->disabled($isEditing),
                        Select::make('personal')
                            ->label('Abogado(s) asignado(s)')
                            ->relationship(
                                'personal',
                                'nombres',
                                modifyQueryUsing: fn ($query) => $query->where('profesion', 'Abogado'),
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nombre_completo)
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->disabled($isEditing),
                        DatePicker::make('fecha_inicio')->required()->default(now())->disabled($isEditing),
                        TextInput::make('duracion_meses')
                            ->label('Duración del proceso (meses)')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->disabled($isEditing),
                        DatePicker::make('fecha_fin')->label('Fecha estimada de fin (aprox.)')->disabled($isEditing),
                    ])
                    ->columns(['default' => 1, 'lg' => 2])
                    ->columnSpanFull(),

                // Lo que no está en el paso 2: solo al crear un caso suelto
                // (en la ficha se quitó a pedido). El estado se cambia con
                // Cerrar / Reactivar caso.
                Section::make('Otros datos del caso')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        Select::make('estado')
                            // "Eliminado" no se elige a mano: lo pone eliminar el caso.
                            ->options([EstadoCaso::Activo->value => EstadoCaso::Activo->getLabel(), EstadoCaso::Cerrado->value => EstadoCaso::Cerrado->getLabel()])
                            ->default(EstadoCaso::Activo)
                            ->required()
                            ->disabled($isEditing),
                        TextInput::make('ciudad')->disabled($isEditing),
                        CamposDenuncia::denunciado()->disabled($isEditing),
                        CamposDenuncia::denuncias(conRelacion: true)->disabled($isEditing),
                    ])
                    ->columns(['default' => 1, 'lg' => 2])
                    ->columnSpanFull(),

                // Solo al crear un caso suelto: en la ficha no se muestra (a
                // pedido) — los montos se ven en la pestaña Pagos.
                Section::make('Finanzas')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        TextInput::make('iguala')->numeric()->prefix('Bs.')->default(0)->required()->disabled($isEditing),
                        TextInput::make('saldo')->numeric()->prefix('Bs.')->default(0)->required()->disabled($isEditing),
                        TextInput::make('pagado')->numeric()->prefix('Bs.')->default(0)->required()->disabled($isEditing),
                    ]),
            ]);
    }
}
