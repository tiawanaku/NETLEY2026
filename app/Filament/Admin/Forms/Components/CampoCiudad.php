<?php

namespace App\Filament\Admin\Forms\Components;

use Closure;
use Filament\Forms\Components\TextInput;

/**
 * Campo "Ciudad" con lista desplegable de los 9 departamentos de Bolivia
 * (más las sugerencias extra que se le pasen) y, en el mismo cuadro, la
 * opción de escribir cualquier otro valor a mano: se escribe, la lista se
 * filtra, y si nada coincide queda lo escrito.
 *
 * Es un TextInput normal (validación, bloqueo, navegación con Enter…) con
 * la lista agregada alrededor (Alpine, ver toEmbeddedHtml()), sin depender
 * del <datalist> del navegador, que casi no se nota y Chrome tapa con sus
 * direcciones guardadas.
 */
class CampoCiudad extends TextInput
{
    public const DEPARTAMENTOS = [
        'Beni', 'Chuquisaca', 'Cochabamba', 'La Paz', 'Oruro', 'Pando', 'Potosí', 'Santa Cruz', 'Tarija',
    ];

    /** @var array<int, string>|Closure */
    protected array|Closure $sugerenciasExtra = [];

    protected bool|Closure $conDepartamentos = true;

    public static function make(?string $name = 'ciudad'): static
    {
        return parent::make($name)
            ->label('Ciudad')
            ->maxLength(100)
            ->placeholder('Elige un departamento o escribe la ciudad')
            ->autocomplete('off')
            ->suffixIcon('heroicon-m-chevron-down')
            ->extraInputAttributes(['data-enter-nav' => 'true']);
    }

    /** Sugerencias además de los departamentos (ej. ciudades de la provincia). */
    public function sugerencias(array|Closure $sugerencias): static
    {
        $this->sugerenciasExtra = $sugerencias;

        return $this;
    }

    /** Mostrar o no los departamentos de Bolivia (ej. solo si el país es Bolivia). */
    public function conDepartamentos(bool|Closure $condicion = true): static
    {
        $this->conDepartamentos = $condicion;

        return $this;
    }

    /** @return array<int, string> */
    public function getOpciones(): array
    {
        return collect($this->evaluate($this->conDepartamentos) ? self::DEPARTAMENTOS : [])
            ->merge((array) $this->evaluate($this->sugerenciasExtra))
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique(fn ($v) => mb_strtolower($v))
            ->values()
            ->all();
    }

    public function toEmbeddedHtml(): string
    {
        $campo = parent::toEmbeddedHtml();

        if ($this->isDisabled() || $this->isReadOnly()) {
            return $campo;
        }

        $opciones = e(json_encode($this->getOpciones(), JSON_UNESCAPED_UNICODE));
        $id = e($this->getId());

        return <<<HTML
            <div class="netley-combo" x-data="netleyCombo({$opciones}, '{$id}')" x-on:click.outside="cerrar()" x-on:focusin="abrir()" x-on:input="filtrar()" x-on:keydown="tecla(\$event)">
                {$campo}
                <ul class="netley-combo-lista" x-show="abierto && visibles.length" x-cloak role="listbox">
                    <template x-for="(opcion, i) in visibles" :key="opcion">
                        <li role="option" x-text="opcion" :class="{ 'netley-combo-activa': i === activa }"
                            x-on:mousedown.prevent="elegir(opcion)" x-on:mouseenter="activa = i"></li>
                    </template>
                </ul>
            </div>
            HTML;
    }
}
