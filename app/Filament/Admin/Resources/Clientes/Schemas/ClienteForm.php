<?php

namespace App\Filament\Admin\Resources\Clientes\Schemas;

use App\Filament\Admin\Support\CamposCi;
use App\Filament\Admin\Support\CamposDomicilio;
use App\Filament\Admin\Support\CamposTelefono;
use App\Filament\Admin\Support\ReglasContacto;
use App\Models\Cliente;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha del cliente ("Datos personales"): mismos campos, en el mismo orden y
 * con la misma disposición en dos columnas que el paso 1 ("Validación de
 * datos") del formulario de Cliente ejecutivo (ClienteCasoWizard::steps()).
 * De solo lectura: /clientes/{id}/edit sirve para CONSULTAR el registro.
 */
class ClienteForm
{
    public static function configure(Schema $schema): Schema
    {
        $isEditing = fn (string $operation): bool => $operation === 'edit';

        return $schema
            ->components([
                Section::make('Datos del cliente')
                    ->schema([
                        TextInput::make('nombres')->required()->maxLength(60)->disabled($isEditing),
                        TextInput::make('ap_paterno')->label('Apellido paterno')->required()->maxLength(30)->disabled($isEditing),
                        TextInput::make('ap_materno')->label('Apellido materno')->maxLength(30)->disabled($isEditing),
                        // No repetir con OTRO cliente (ver App\Rules\ContactoUnico).
                        CamposTelefono::make('telefono', deshabilitado: $isEditing, unicoEn: Cliente::class, entidadUnica: 'otro cliente'),
                        CamposTelefono::make('whatsapp', deshabilitado: $isEditing, unicoEn: Cliente::class, entidadUnica: 'otro cliente'),
                        TextInput::make('correo')->email()->disabled($isEditing)->rules([ReglasContacto::correo(Cliente::class, 'otro cliente')]),
                        CamposCi::make(bloqueado: $isEditing),
                        DatePicker::make('fecha_nacimiento')->label('Fecha de nacimiento')->maxDate(now())->disabled($isEditing),
                        ...CamposDomicilio::region($isEditing),
                        // Ver .netley-campo-ancho-alineado en admin-theme.css
                        // (igual que en el paso 1).
                        TextInput::make('direccion')
                            ->label('Dirección')
                            ->columnSpanFull()
                            ->disabled($isEditing)
                            ->extraFieldWrapperAttributes(['class' => 'netley-campo-ancho-alineado']),
                        ...CamposDomicilio::detalle($isEditing),
                        CamposDomicilio::mapa($isEditing)->helperText(null)->defaultZoom(16),
                    ])
                    ->columns(2)
                    ->inlineLabel()
                    ->columnSpanFull(),
            ]);
    }
}
