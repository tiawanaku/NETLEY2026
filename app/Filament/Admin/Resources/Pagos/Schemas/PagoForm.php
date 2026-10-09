<?php

namespace App\Filament\Admin\Resources\Pagos\Schemas;

use App\Filament\Admin\Support\CamposPago;
use App\Models\Caso;
use App\Models\Cliente;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PagoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel()
            ->columns(1)
            ->components([
                Select::make('cliente_id')
                    ->label('Cliente')
                    ->options(fn () => Cliente::query()->get()->mapWithKeys(
                        fn (Cliente $c) => [$c->id => "{$c->nombre_completo} — CI {$c->ci}"]
                    ))
                    ->searchable()
                    ->live()
                    ->required()
                    ->afterStateUpdated(fn ($set) => $set('caso_id', null)),

                Select::make('caso_id')
                    ->label('Caso (con saldo pendiente)')
                    ->options(function (Get $get) {
                        if (! $get('cliente_id')) {
                            return [];
                        }

                        return Caso::query()
                            ->where('cliente_id', $get('cliente_id'))
                            ->where('estado', '!=', 'cerrado')
                            ->where('saldo', '>', 0)
                            ->get()
                            ->mapWithKeys(fn (Caso $c) => [$c->id => "Caso #{$c->id} — {$c->especialidad->getLabel()} — saldo Bs. {$c->saldo}"]);
                    })
                    ->disabled(fn (Get $get) => blank($get('cliente_id')))
                    ->required(),

                TextInput::make('monto')->numeric()->prefix('Bs.')->required(),
                ...CamposPago::components(),
                DatePicker::make('fecha_pago')->default(now())->required(),
                Select::make('sucursal')
                    ->options(['LA PAZ' => 'La Paz', 'SANTA CRUZ' => 'Santa Cruz', 'COCHABAMBA' => 'Cochabamba'])
                    ->default('LA PAZ')
                    ->required(),
            ]);
    }
}
