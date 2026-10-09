<?php

namespace App\Filament\Admin\Support;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Campos compartidos de un pago (tipo, forma, cheque y banco) que aparecen en
 * todos los formularios donde se registra un pago, igual que en el comprobante
 * de ingreso original.
 */
class CamposPago
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function components(bool $conTipo = true): array
    {
        $campos = [];

        if ($conTipo) {
            $campos[] = Select::make('tipo_pago')
                ->label('Tipo de pago')
                ->options([
                    'iguala' => 'Iguala profesional',
                    'anticipo' => 'Anticipo cliente',
                    'delegado' => 'Pago delegado (HIH)',
                    'a_cuenta' => 'A cuenta',
                ])
                ->default('a_cuenta')
                ->required()
                ->extraInputAttributes(['data-enter-nav' => 'true']);
        }

        return [
            ...$campos,
            Select::make('forma_pago')
                ->label('Forma de pago')
                ->options([
                    'efectivo' => 'Efectivo',
                    'qr' => 'QR',
                    'cheque' => 'Cheque',
                    'banco' => 'Banco (transferencia)',
                ])
                ->default('efectivo')
                ->live()
                ->required()
                ->extraInputAttributes(['data-enter-nav' => 'true', 'data-enter-nav-live' => 'true']),
            TextInput::make('nro_cheque')
                ->label('Cheque N°')
                ->maxLength(50)
                ->visible(fn (Get $get) => $get('forma_pago') === 'cheque')
                ->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('banco')
                ->label('Banco')
                ->maxLength(100)
                ->visible(fn (Get $get) => in_array($get('forma_pago'), ['cheque', 'banco'], true))
                ->extraInputAttributes(['data-enter-nav' => 'true']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function datos(array $data, ?string $tipoFijo = null): array
    {
        $forma = $data['forma_pago'] ?? 'efectivo';
        $tieneCheque = $forma === 'cheque';
        $tieneBanco = in_array($forma, ['cheque', 'banco'], true);

        return [
            'tipo_pago' => $tipoFijo ?? ($data['tipo_pago'] ?? 'a_cuenta'),
            'forma_pago' => $forma,
            'nro_cheque' => $tieneCheque ? ($data['nro_cheque'] ?? null) : null,
            'banco' => $tieneBanco ? ($data['banco'] ?? null) : null,
        ];
    }
}
