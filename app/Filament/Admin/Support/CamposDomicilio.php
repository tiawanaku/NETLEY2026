<?php

namespace App\Filament\Admin\Support;

use Afsakar\LeafletMapPicker\LeafletMapPicker;
use App\Support\PaisesCiudades;
use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Campos de domicilio compartidos por Consulta, el wizard "Cliente
 * Ejecutivo"/"Crear cliente" y la ficha del cliente, para que la consulta
 * capture lo mismo que luego se pasa al cliente.
 *
 * $bloqueado acepta lo mismo que ->disabled() de cada formulario (bool o
 * closure con $operation/$livewire).
 */
class CamposDomicilio
{
    /** Centro inicial del mapa cuando aún no hay punto: La Paz. */
    public const CENTRO_MAPA = ['lat' => -16.4955, 'lng' => -68.1336];

    /** Columnas que la consulta pasa tal cual al cliente. */
    public const COLUMNAS = [
        'pais',
        'provincia',
        'ciudad',
        'direccion',
        'zona',
        'calles',
        'numero_domicilio',
        'indicaciones_domicilio',
        'ubicacion',
    ];

    /**
     * País/Provincia/Ciudad usan la API pública countriesnow.space como
     * sugerencias (datalist), en cascada; siguen siendo texto libre porque
     * esa API no cubre poblaciones pequeñas de Bolivia.
     *
     * @return array<int, TextInput>
     */
    public static function region(bool|Closure $bloqueado = false): array
    {
        return [
            TextInput::make('pais')
                ->label('País')
                ->default('Bolivia')
                ->live(onBlur: true)
                ->datalist(fn () => PaisesCiudades::paises())
                ->regex('/^[^0-9]*$/')
                ->disabled($bloqueado)
                ->extraInputAttributes(self::atributosSoloLetras()),
            TextInput::make('provincia')
                ->live(onBlur: true)
                ->datalist(fn (Get $get) => PaisesCiudades::provincias($get('pais')))
                ->regex('/^[^0-9]*$/')
                ->disabled($bloqueado)
                ->extraInputAttributes(self::atributosSoloLetras()),
            TextInput::make('ciudad')
                ->datalist(fn (Get $get) => PaisesCiudades::ciudades($get('pais'), $get('provincia')))
                ->regex('/^[^0-9]*$/')
                ->disabled($bloqueado)
                ->extraInputAttributes(self::atributosSoloLetras()),
        ];
    }

    /**
     * @return array<int, TextInput|Textarea>
     */
    public static function detalle(bool|Closure $bloqueado = false): array
    {
        return [
            TextInput::make('zona')->label('Zona / barrio')->maxLength(100)->disabled($bloqueado)->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('calles')->label('Calle(s)')->maxLength(200)->placeholder('Ej. Av. Arce esq. Calle 2')->disabled($bloqueado)->extraInputAttributes(['data-enter-nav' => 'true']),
            TextInput::make('numero_domicilio')->label('N° casa / depto.')->maxLength(50)->placeholder('Ej. 1234, Edif. Sol, Piso 3, Dpto. B')->disabled($bloqueado)->extraInputAttributes(['data-enter-nav' => 'true']),
            Textarea::make('indicaciones_domicilio')
                ->label('Indicaciones')
                ->placeholder('Referencias para llegar: color de la fachada, a lado de…, portón negro, etc.')
                ->maxLength(500)
                ->rows(2)
                ->disabled($bloqueado)
                ->columnSpanFull(),
        ];
    }

    /**
     * El plugin solo mira readOnly()/disabled() como valor fijo (no evalúa
     * closures), así que el bloqueo dinámico se aplica apagando el clic y el
     * arrastre del marcador.
     */
    public static function mapa(bool|Closure $bloqueado = false): LeafletMapPicker
    {
        $editable = $bloqueado instanceof Closure
            ? fn (LeafletMapPicker $component) => ! $component->evaluate($bloqueado)
            : ! $bloqueado;

        $mapa = LeafletMapPicker::make('ubicacion');

        // El plugin arma su config una sola vez (wire:ignore): al pasar de
        // lectura a edición (botón "Editar" de la consulta) seguía sin
        // permitir clic. Una clave distinta por modo hace que Livewire lo
        // vuelva a dibujar con la config nueva.
        if ($bloqueado instanceof Closure) {
            $mapa->key(fn (LeafletMapPicker $component) => 'ubicacion-'.($component->evaluate($bloqueado) ? 'lectura' : 'edicion'));
        }

        return $mapa
            ->label('Ubicación del domicilio')
            ->helperText('Haz clic en el mapa (o arrastra el marcador) para marcar el domicilio.')
            ->defaultLocation(self::CENTRO_MAPA)
            ->defaultZoom(13)
            ->height('320px')
            ->draggable($editable)
            ->clickable($editable)
            ->myLocationButtonLabel('Mi ubicación')
            ->customMarker(self::marcadorMapa())
            ->columnSpanFull()
            ->inlineLabel(false);
    }

    /**
     * Marcador del mapa: el ícono heroicon-s-map-pin de Filament como imagen
     * SVG (Leaflet necesita una URL de imagen).
     *
     * @return array<string, mixed>
     */
    public static function marcadorMapa(): array
    {
        $svg = str_replace(
            'fill="currentColor"',
            'fill="#dc2626" stroke="#ffffff" stroke-width="1" width="40" height="40"',
            svg('heroicon-s-map-pin')->toHtml(),
        );

        return [
            'iconUrl' => 'data:image/svg+xml;base64,'.base64_encode($svg),
            'iconSize' => [40, 40],
            'iconAnchor' => [20, 38],
            'popupAnchor' => [0, -38],
        ];
    }

    /**
     * Solo se guarda un punto marcado de verdad; si no se tocó el mapa (o
     * llega algo inválido) queda sin ubicación.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function ubicacionValida(mixed $ubicacion): ?array
    {
        $lat = $ubicacion['lat'] ?? null;
        $lng = $ubicacion['lng'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)
            || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    /**
     * @return array<string, string>
     */
    protected static function atributosSoloLetras(): array
    {
        return [
            'data-enter-nav' => 'true',
            'oninput' => 'this.value=this.value.replace(/[0-9]/g,\'\')',
            // Evita que Chrome ofrezca direcciones guardadas encima de las
            // sugerencias del <datalist> (detecta "pais"/"ciudad" como
            // campos de dirección).
            'autocomplete' => 'off',
        ];
    }
}
