<?php

namespace App\Filament\Admin\Support;

use App\Enums\EstadoCaso;
use App\Filament\Admin\Pages\AgendaCalendario;
use App\Filament\Admin\Resources\Casos\CasoResource;
use App\Filament\Admin\Resources\Citas\CitaResource;
use App\Filament\Admin\Resources\Clientes\ClienteResource;
use App\Filament\Admin\Resources\Consultas\ConsultaResource;
use App\Filament\Admin\Resources\Personals\PersonalResource;
use App\Models\Caso;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Personal;

/**
 * Ruta de navegación (migas de pan) de cada pantalla, armada según la
 * sección del menú lateral a la que pertenece: "Consultas › Agendados ›
 * N° 80", "Clientes ejecutivos › ID 49 › Proceso N° 73",
 * etc. Cada escalón (menos el último) lleva a su pantalla. Ver
 * App\Filament\Admin\Concerns\MigasDeNavegacion.
 */
class Migas
{
    /** Nombre de cada pestaña de Consultas, como en el menú. */
    public const PESTANAS_CONSULTAS = [
        'nuevas' => 'Nuevas consultas',
        'agendados' => 'Agendados',
        'cerradas' => 'Consultas cerradas',
    ];

    public const PESTANAS_PERSONAL = [
        'vigentes' => 'Vigentes',
        'retirados' => 'Retirados',
    ];

    public const PESTANAS_CASOS = [
        'todos' => 'Casos',
        'cerrados' => 'Casos cerrados',
        'eliminados' => 'Casos eliminados',
    ];

    /**
     * @param  array<int, array{0: string, 1?: ?string}>  $escalones  [texto, url]
     * @return array<string|int, string>
     */
    public static function armar(array $escalones): array
    {
        $migas = [];

        foreach ($escalones as $escalon) {
            [$texto, $url] = [$escalon[0], $escalon[1] ?? null];

            if ($url) {
                $migas[$url] = $texto;
            } else {
                $migas[] = $texto;
            }
        }

        return $migas;
    }

    /**
     * Pestaña de Consultas a la que pertenece una consulta, mismo criterio
     * que ListConsultas::getTabs(): cerrada, agendada (abierta con cita
     * vigente) o nueva.
     */
    public static function pestanaConsulta(Consulta $consulta): string
    {
        $abierta = in_array($consulta->estado, \App\Enums\EstadoConsulta::abiertos(), true);

        if (! $abierta && $consulta->estado !== \App\Enums\EstadoConsulta::CasoIniciado && $consulta->caso_id === null) {
            return 'cerradas';
        }

        if ($abierta && $consulta->caso_id === null && $consulta->citas()->where('anulada', false)->exists()) {
            return 'agendados';
        }

        return 'nuevas';
    }

    /** Consultas › {pestaña} › N° X › {extra} */
    public static function consultas(?string $pestana = null, ?Consulta $consulta = null, ?string $extra = null): array
    {
        $base = ConsultaResource::getUrl('index');
        $escalones = [['Consultas']];

        if ($pestana && isset(self::PESTANAS_CONSULTAS[$pestana])) {
            $escalones[] = [self::PESTANAS_CONSULTAS[$pestana], $consulta || $extra ? $base.'?tab='.$pestana : null];
        }

        if ($consulta) {
            $escalones[] = ['N° '.$consulta->getKey(), $extra ? ConsultaResource::getUrl($pestana === 'agendados' ? 'verAgendado' : 'edit', ['record' => $consulta]) : null];
        }

        if ($extra) {
            $escalones[] = [$extra];
        }

        return $escalones;
    }

    /** Personal › {Vigentes|Retirados} › Nombre */
    public static function personal(?string $pestana = null, ?Personal $persona = null, ?string $extra = null): array
    {
        $base = PersonalResource::getUrl('index');
        $escalones = [['Personal']];

        if ($pestana && isset(self::PESTANAS_PERSONAL[$pestana])) {
            $escalones[] = [self::PESTANAS_PERSONAL[$pestana], $persona || $extra ? $base.'?tab='.$pestana : null];
        }

        if ($persona) {
            $escalones[] = [$persona->nombre_completo];
        }

        if ($extra) {
            $escalones[] = [$extra];
        }

        return $escalones;
    }

    /**
     * Clientes ejecutivos › ID 49 (sin el grupo "Cliente Ejecutivo" delante,
     * que repetía casi lo mismo; el cliente por su ID, no por el nombre).
     */
    public static function clientes(?Cliente $cliente = null, ?string $extra = null): array
    {
        $escalones = [
            ['Clientes ejecutivos', $cliente || $extra ? ClienteResource::getUrl('index') : null],
        ];

        if ($cliente) {
            $escalones[] = [self::idCliente($cliente), $extra ? ClienteResource::getUrl('edit', ['record' => $cliente]) : null];
        }

        if ($extra) {
            $escalones[] = [$extra];
        }

        return $escalones;
    }

    /**
     * Ficha del caso según dónde está en el menú:
     * - eliminado: Cliente Ejecutivo › Casos eliminados › Proceso N° X
     * - cerrado:   Cliente Ejecutivo › Casos cerrados › Proceso N° X
     * - activo:    Clientes ejecutivos › ID cliente › Proceso N° X
     */
    public static function caso(Caso $caso): array
    {
        $base = CasoResource::getUrl('index');
        $titulo = 'Proceso N° '.$caso->getKey();

        if ($caso->trashed()) {
            return [['Cliente Ejecutivo'], ['Casos eliminados', $base.'?tab=eliminados'], [$titulo]];
        }

        // "Pendiente" (falta iniciar costas y costos) va como un caso de la lista general.
        if ($caso->estado === EstadoCaso::Cerrado) {
            return [['Cliente Ejecutivo'], ['Casos cerrados', ClienteResource::getUrl('cerrados')], [$titulo]];
        }

        $escalones = [['Clientes ejecutivos', ClienteResource::getUrl('index')]];

        if ($caso->cliente) {
            $escalones[] = [self::idCliente($caso->cliente), ClienteResource::getUrl('edit', ['record' => $caso->cliente_id]).'?relation=0'];
        }

        $escalones[] = [$titulo];

        return $escalones;
    }

    /** El cliente en las migas: "ID 49" (el ID cliente del listado). */
    public static function idCliente(Cliente $cliente): string
    {
        return 'ID '.($cliente->nro_cliente ?? $cliente->getKey());
    }

    /** Listado de casos: Cliente Ejecutivo › {Casos cerrados|Casos eliminados|Casos} */
    public static function listadoCasos(?string $pestana): array
    {
        return [
            ['Cliente Ejecutivo'],
            [match ($pestana) {
                'cerrados' => 'Casos cerrados',
                'eliminados' => 'Casos eliminados',
                default => 'Casos',
            }],
        ];
    }

    /** Agenda › {Calendario|Lista} › … */
    public static function agenda(string $vista, ?string $extra = null): array
    {
        $escalones = [['Agenda', AgendaCalendario::getUrl()]];

        $escalones[] = $vista === 'lista'
            ? ['Lista', $extra ? CitaResource::getUrl('index') : null]
            : ['Calendario'];

        if ($extra) {
            $escalones[] = [$extra];
        }

        return $escalones;
    }
}
