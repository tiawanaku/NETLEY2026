<?php

namespace App\Filament\Admin\Support;

use App\Support\CodigosTelefonicos;
use App\Support\Telefono;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Campo de teléfono/WhatsApp de todo el sistema, estilo iPhone: un selector
 * de código de país ("+591 (Bolivia)", "+1 (EE. UU. / Canadá)"…, sin
 * banderas, con búsqueda) fusionado con el número.
 *
 * Se guarda en la misma columna como "+591 71234567" (ver
 * App\Support\Telefono::componer). El select "<campo>_codigo" no se guarda
 * aparte (dehydrated(false)): al abrir el formulario el valor guardado se
 * separa en código + número, y al guardar se vuelven a unir. Un dato antiguo
 * sin código se abre como +591.
 */
class CamposTelefono
{
    /**
     * @param  class-string<Model>|null  $unicoEn  Modelo en el que el número no se puede repetir: ni como teléfono ni como WhatsApp de otro registro (ignora el registro editado).
     * @param  string  $entidadUnica  Cómo se nombra al dueño del número en el error ("otro personal", "otro cliente", "otra consulta").
     * @param  (Closure(Builder, Get): mixed)|null  $alcanceUnico  Restricción extra de la búsqueda (ver ReglasContacto).
     */
    public static function make(
        string $campo,
        ?string $etiqueta = null,
        bool|Closure $requerido = false,
        bool|Closure $deshabilitado = false,
        ?string $unicoEn = null,
        ?string $mensajeUnico = null,
        string $entidadUnica = 'otro registro',
        ?Closure $alcanceUnico = null,
    ): FusedGroup {
        $campoCodigo = $campo.'_codigo';
        $etiqueta ??= self::etiqueta($campo);

        $numero = TextInput::make($campo)
            ->label($etiqueta)
            ->hiddenLabel()
            ->tel()
            ->placeholder('Número')
            ->required($requerido)
            ->disabled($deshabilitado)
            ->maxLength(14)
            ->regex('/^[0-9]{6,14}$/')
            ->validationMessages(['regex' => 'Ingresa solo números (de 6 a 14 dígitos), sin el código de país.'])
            ->extraInputAttributes(self::atributos())
            ->columnSpan(1)
            // El código del select se carga acá (no en el select): este
            // campo va después, así que su hook corre con el valor guardado
            // completo todavía intacto.
            ->afterStateHydrated(function (TextInput $component, ?string $state, Set $set) use ($campoCodigo): void {
                $partes = Telefono::descomponer($state);

                // Código que no está en el catálogo (dato antiguo raro): se
                // deja el valor guardado tal cual a la vista para corregirlo.
                if (! in_array($partes['codigo'], CodigosTelefonicos::codigos(), true)) {
                    $partes = ['codigo' => CodigosTelefonicos::POR_DEFECTO, 'numero' => $state];
                }

                $set($campoCodigo, $partes['codigo']);
                $component->state($partes['numero']);
            })
            ->dehydrateStateUsing(fn (?string $state, Get $get): ?string => Telefono::componer($get($campoCodigo), $state));

        if ($unicoEn !== null) {
            // No repetir el número de OTRO registro de la misma entidad
            // (compara con su teléfono y su WhatsApp, normalizado por
            // dígitos; ver App\Rules\ContactoUnico).
            $numero->rules([ReglasContacto::telefono($unicoEn, $campoCodigo, $entidadUnica, $alcanceUnico, $mensajeUnico)]);
        }

        return FusedGroup::make([
            Select::make($campoCodigo)
                ->label('Código de país')
                ->hiddenLabel()
                ->options(CodigosTelefonicos::opciones())
                ->default(CodigosTelefonicos::POR_DEFECTO)
                ->searchable()
                // Busca también por alias ("Estados Unidos", "USA"…), no solo por la etiqueta.
                ->getSearchResultsUsing(fn (?string $search): array => CodigosTelefonicos::buscar($search))
                ->getOptionLabelUsing(fn (?string $value): ?string => CodigosTelefonicos::opciones()[$value] ?? $value)
                ->selectablePlaceholder(false)
                ->wrapOptionLabels(false)
                ->disabled($deshabilitado)
                ->dehydrated(false)
                ->columnSpan(1),
            $numero,
        ])
            ->label($etiqueta)
            ->columns(['default' => 2])
            // El ancho del selector se ajusta a lo elegido (ver admin-theme.css).
            ->extraAttributes(['class' => 'campo-telefono']);
    }

    /**
     * Atributos del input del número: navegación con Enter y solo dígitos.
     *
     * @return array<string, string>
     */
    public static function atributos(): array
    {
        return [
            'data-enter-nav' => 'true',
            'inputmode' => 'numeric',
            'oninput' => 'this.value=this.value.replace(/[^0-9]/g,\'\')',
        ];
    }

    private static function etiqueta(string $campo): string
    {
        return match ($campo) {
            'telefono' => 'Teléfono',
            'whatsapp' => 'WhatsApp',
            default => str($campo)->replace('telefono', 'teléfono')->replace('_', ' ')->ucfirst()->toString(),
        };
    }
}
