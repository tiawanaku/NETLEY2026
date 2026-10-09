<?php

namespace App\Filament\Admin\Resources\Consultas\Pages;

use App\Filament\Admin\Resources\Consultas\ConsultaResource;
use App\Filament\Admin\Resources\Consultas\Schemas\ConsultaForm;
use App\Filament\Admin\Support\RespuestaFields;
use App\Models\Consulta;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class CreateConsulta extends CreateRecord
{
    protected static string $resource = ConsultaResource::class;

    public function form(Schema $schema): Schema
    {
        return ConsultaForm::configure($schema, before: [
            Placeholder::make('numero_consulta')
                ->hiddenLabel()
                ->inlineLabel(false)
                ->columnSpanFull()
                ->content(fn () => new HtmlString(
                    '<div style="text-align:center;font-weight:700;font-size:18px;color:#1a2537;">N° de consulta: '.$this->siguienteNumero().'</div>'
                )),
        ]);
    }

    /**
     * "Fecha consulta" solo pide la fecha en el formulario; la hora no se le
     * pregunta al usuario, se registra sola con el momento real de alta.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (filled($data['fecha_consulta'] ?? null)) {
            $data['fecha_consulta'] = Carbon::parse($data['fecha_consulta'])->setTimeFrom(now());
        }

        return $data;
    }

    /**
     * Si se activó el switch "Respuesta", además de crear la consulta se
     * registra de una vez la respuesta (materia legal, delito y texto) con
     * los mismos campos que usa el botón "Responder".
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Consulta $consulta */
        $consulta = static::getModel()::create($data);

        if (($data['tiene_respuesta'] ?? false) && filled($data['respuesta'] ?? null)) {
            $consulta->respuestas()->create([
                ...RespuestaFields::datosParaGuardar($data),
                'personal_id' => auth()->user()?->personal_id,
                'fecha_respuesta' => now(),
                'paso' => 'respondido',
            ]);
        }

        return $consulta;
    }

    /** Después de guardar, volver al listado de Consultas en vez de abrir la edición. */
    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('index');
    }

    /** Botones únicamente al pie del formulario: Guardar y Cancelar. */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction()->label('Cancelar'),
        ];
    }

    /** Cancelar siempre vuelve a la lista de Consultas, no a la página anterior. */
    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->url($this->getResourceUrl('index'))
            ->alpineClickHandler(null);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Guardar')
            ->submit(null)
            ->action(fn () => $this->create())
            ->extraAttributes(['data-enter-submit' => 'true']);
    }

    /** El ID que le va a corresponder a la consulta que se está por crear. */
    protected function siguienteNumero(): int|string
    {
        return DB::select("SHOW TABLE STATUS LIKE 'consultas'")[0]->Auto_increment ?? '—';
    }
}
