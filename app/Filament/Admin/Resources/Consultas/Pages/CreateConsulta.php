<?php

namespace App\Filament\Admin\Resources\Consultas\Pages;

use App\Enums\EstadoConsulta;
use App\Filament\Admin\Resources\Consultas\ConsultaResource;
use App\Filament\Admin\Resources\Consultas\Schemas\ConsultaForm;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
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
     *
     * El texto de la consulta, el estado y el seguimiento (fecha/origen) ya
     * no se piden al crear (ver ConsultaForm): quedan con un valor por
     * defecto y se completan después desde la ficha.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['consulta'] ??= '';
        $data['estado'] ??= EstadoConsulta::Pendiente->value;
        $data['fecha_consulta'] = Carbon::parse($data['fecha_consulta'] ?? now())->setTimeFrom(now());

        // Una consulta que entra por WhatsApp nace "Agendado" (regla de
        // negocio, se aplica en el servidor aunque se elija otro estado).
        if (EstadoConsulta::esOrigenWhatsapp($data['origen'] ?? null)) {
            $data['estado'] = EstadoConsulta::Agendado->value;
        }

        return $data;
    }

    /**
     * Después de guardar, abrir la ficha de la consulta recién creada con la
     * ventana de "Agendar" ya abierta (ver EditConsulta::mount()) — la misma
     * que aparece en la ficha de cualquier consulta, para agendar la cita de
     * una vez sin tener que buscarla de nuevo.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('edit', ['record' => $this->getRecord()]).'?agendar=1';
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
