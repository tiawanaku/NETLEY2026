<?php

namespace App\Filament\Admin\Resources\Clientes\Schemas;

use App\Filament\Admin\Support\CamposDomicilio;
use App\Filament\Admin\Support\CamposTelefono;
use App\Filament\Admin\Support\ReglasContacto;
use App\Models\Cliente;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClienteForm
{
    public static function configure(Schema $schema): Schema
    {
        // Igual que en Consultas: la ficha del cliente queda de solo lectura
        // una vez creada — /clientes/{id}/edit sirve para CONSULTAR el
        // registro, no para reescribirlo.
        $isEditing = fn (string $operation): bool => $operation === 'edit';

        return $schema
            ->inlineLabel()
            ->components([
                Section::make('Datos del cliente')
                    ->schema([
                        TextInput::make('nro_cliente')->label('N° de cliente')->disabled()->dehydrated(false)->visibleOn('edit'),
                        TextInput::make('nombres')->required()->maxLength(60)->disabled($isEditing),
                        TextInput::make('ap_paterno')->label('Apellido paterno')->required()->maxLength(30)->disabled($isEditing),
                        TextInput::make('ap_materno')->label('Apellido materno')->maxLength(30)->disabled($isEditing),
                        TextInput::make('ci')->label('Carnet de identidad')->required()->disabled($isEditing)
                            // scopedUnique() (no unique()): así el mensaje
                            // personalizado también se muestra al validar en
                            // tiempo real (->unique() solo lo toma en cuenta
                            // al guardar el formulario completo).
                            ->scopedUnique(model: Cliente::class, ignoreRecord: true)
                            ->validationMessages(['unique' => 'Ya existe un cliente registrado con este carnet de identidad. Revisa el dato.'])
                            // Valida apenas se completa el campo (al perder el foco), no
                            // recién al guardar.
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (TextInput $component) => $component->getLivewire()->validateOnly($component->getStatePath())),
                        TextInput::make('extension')->label('Extensión CI')->maxLength(20)->disabled($isEditing),
                        Select::make('sucursal')
                            ->options(['LA PAZ' => 'La Paz', 'SANTA CRUZ' => 'Santa Cruz', 'COCHABAMBA' => 'Cochabamba'])
                            ->default('LA PAZ')
                            ->required()
                            ->disabled($isEditing),
                    ]),

                Section::make('Contacto')
                    ->schema([
                        // No repetir con OTRO cliente (ver App\Rules\ContactoUnico).
                        CamposTelefono::make('telefono', deshabilitado: $isEditing, unicoEn: Cliente::class, entidadUnica: 'otro cliente'),
                        CamposTelefono::make('whatsapp', deshabilitado: $isEditing, unicoEn: Cliente::class, entidadUnica: 'otro cliente'),
                        TextInput::make('correo')->email()->disabled($isEditing)->rules([ReglasContacto::correo(Cliente::class, 'otro cliente')])
                            // Valida apenas se completa el campo (al perder el foco), no
                            // recién al guardar.
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (TextInput $component) => $component->getLivewire()->validateOnly($component->getStatePath())),
                        ...CamposDomicilio::region($isEditing),
                        TextInput::make('direccion')->label('Dirección')->disabled($isEditing),
                    ]),

                Section::make('Ubicación del domicilio')
                    ->schema([
                        ...CamposDomicilio::detalle($isEditing),
                        CamposDomicilio::mapa($isEditing)->helperText(null)->defaultZoom(16),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
