<?php

namespace App\Filament\Admin\Support;

use App\Enums\Especialidad;
use App\Models\Caso;
use App\Models\Consulta;
use App\Models\Delito;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Campos "Denunciado" (texto libre) y "Denuncias" (varios delitos del
 * catálogo `delitos`) de una consulta o un caso. Se usan en el paso
 * "Proceso" del wizard de Cliente Ejecutivo (ClienteCasoWizard), en las
 * acciones "Agendar" de Consultas y Casos y en las fichas de ambos.
 * Son adicionales al "Delito / Materia específica" (DelitoSelect).
 */
class CamposDenuncia
{
    public static function denunciado(): TextInput
    {
        return TextInput::make('denunciado')
            ->label('Denunciado')
            ->maxLength(255)
            ->extraInputAttributes(['data-enter-nav' => 'true']);
    }

    /**
     * Con $conRelacion el campo carga y guarda la relación
     * delitosDenunciados del registro del formulario (fichas de Consulta y
     * Caso); sin ella, los ids viajan en $data['denuncias'] y se guardan con
     * sincronizar() (wizard y acciones Agendar).
     */
    public static function denuncias(bool $conRelacion = false): Select
    {
        $select = Select::make('denuncias')
            ->label('Denuncias')
            ->options(fn (): array => self::opciones())
            ->multiple()
            ->searchable()
            ->native(false)
            ->helperText('Delitos denunciados; puede elegir varios.')
            ->extraAttributes(['data-enter-nav-field' => 'true']);

        if ($conRelacion) {
            $select
                ->dehydrated(false)
                ->afterStateHydrated(function (Select $component, ?Model $record): void {
                    if ($record instanceof Caso || $record instanceof Consulta) {
                        $component->state(self::ids($record));
                    }
                })
                ->saveRelationshipsUsing(function (Select $component, Model $record, mixed $state): void {
                    // Campo de solo lectura (ficha sin editar): no se toca.
                    if ($component->isDisabled()) {
                        return;
                    }

                    if ($record instanceof Caso || $record instanceof Consulta) {
                        self::sincronizar($record, $state);
                    }
                });
        }

        return $select;
    }

    /**
     * Catálogo agrupado por área, con las mismas etiquetas que "Delito /
     * Materia específica".
     *
     * @return array<string, array<int, string>>
     */
    public static function opciones(): array
    {
        return Delito::query()
            ->orderBy('area')
            ->orderBy('delito')
            ->get(['id', 'area', 'delito'])
            ->groupBy(fn (Delito $delito): string => Especialidad::tryFrom($delito->area)?->getLabel() ?? $delito->area)
            ->map(fn ($delitos): array => $delitos->pluck('delito', 'id')->all())
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function ids(Caso|Consulta $record): array
    {
        return $record->delitosDenunciados()
            ->pluck('delitos.id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    public static function sincronizar(Caso|Consulta $record, mixed $ids): void
    {
        $record->delitosDenunciados()->sync(
            collect((array) $ids)
                ->filter(fn ($id): bool => filled($id))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all()
        );
    }

    /**
     * Tras guardarlos desde una acción (Agendar), actualiza los campos de la
     * ficha abierta (Editar consulta / Editar caso) sin recargar la página.
     */
    public static function refrescarFicha(mixed $livewire, Caso|Consulta $record): void
    {
        if (! $livewire instanceof EditRecord) {
            return;
        }

        $livewire->refreshFormData(['denunciado']);
        $livewire->data['denuncias'] = self::ids($record);
    }
}
