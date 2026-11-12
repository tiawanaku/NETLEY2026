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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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

                Section::make('Caso')
                    ->schema([
                        Select::make('cliente_id')
                            ->label('Cliente')
                            ->relationship('cliente', 'nombres')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nombre_completo.' — CI '.$record->ci)
                            ->searchable(['nombres', 'ap_paterno', 'ci'])
                            ->preload()
                            ->required()
                            ->disabled($isEditing),
                        Select::make('personal')
                            ->label('Abogado(s) asignado(s)')
                            ->relationship(
                                'personal',
                                'nombres',
                                modifyQueryUsing: fn ($query) => $query->where('profesion', 'Abogado'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->disabled($isEditing),
                        ...array_map(fn ($field) => $field->disabled($isEditing), DelitoSelect::make()),
                        // Casos creados con materia "Otros" desde el wizard.
                        TextInput::make('materia_texto')
                            ->label('Materia legal (especificar)')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?string $state) => filled($state)),
                        TextInput::make('delito_texto')
                            ->label('Delito (especificar)')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?string $state) => filled($state)),
                        // Mismas opciones que el wizard; un valor fuera de la lista
                        // (casos legacy, o "Otros" especificado a mano) se agrega
                        // para que se siga viendo en la ficha.
                        // Apersonamiento, estado y ciudad a la izquierda;
                        // "Servicios requeridos" al lado, a su misma altura.
                        Grid::make(['default' => 1, 'lg' => 2])
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
                                    Select::make('estado')
                                        ->options(EstadoCaso::class)
                                        ->default(EstadoCaso::ActivoPendiente)
                                        ->required()
                                        ->disabled($isEditing),
                                    TextInput::make('ciudad')->disabled($isEditing),
                                ]),
                                CamposServicios::campo(disabled: $isEditing),
                            ])
                            ->columnSpanFull(),
                        // En la ficha quedan de solo lectura; se actualizan
                        // desde la acción "Agendar cita".
                        CamposDenuncia::denunciado()->disabled($isEditing),
                        CamposDenuncia::denuncias(conRelacion: true)->disabled($isEditing),
                        Textarea::make('descripcion')->disabled($isEditing)->columnSpanFull(),
                    ])
                    // En dos columnas (a pedido): cliente | abogados,
                    // materia | delito, denunciado | denuncias...
                    ->columns(['default' => 1, 'lg' => 2])
                    ->columnSpanFull(),

                // Solo al crear un caso suelto: en la ficha no se muestra (a
                // pedido) — los montos se ven en la pestaña Pagos.
                Section::make('Finanzas')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        DatePicker::make('fecha_inicio')->required()->default(now())->disabled($isEditing),
                        DatePicker::make('fecha_fin')->label('Vencimiento')->disabled($isEditing),
                        TextInput::make('iguala')->numeric()->prefix('Bs.')->default(0)->required()->disabled($isEditing),
                        TextInput::make('saldo')->numeric()->prefix('Bs.')->default(0)->required()->disabled($isEditing),
                        TextInput::make('pagado')->numeric()->prefix('Bs.')->default(0)->required()->disabled($isEditing),
                    ]),
            ]);
    }
}
