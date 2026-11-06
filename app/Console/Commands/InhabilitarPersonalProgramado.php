<?php

namespace App\Console\Commands;

use App\Enums\EstadoPersonal;
use App\Models\Personal;
use Illuminate\Console\Command;

/**
 * Inhabilita al personal cuya baja se programó para una fecha futura (ver
 * PersonalActions::toggleEstado()) apenas esa fecha llega. Se corre a
 * diario desde el scheduler (ver bootstrap/app.php).
 */
class InhabilitarPersonalProgramado extends Command
{
    protected $signature = 'personal:inhabilitar-programados';

    protected $description = 'Inhabilita al personal cuya fecha de baja programada ya llegó';

    public function handle(): int
    {
        $afectados = Personal::query()
            ->where('estado', EstadoPersonal::Habilitado)
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<=', now()->toDateString())
            ->get();

        foreach ($afectados as $personal) {
            $personal->update(['estado' => EstadoPersonal::Inhabilitado]);
        }

        $this->info("Personal inhabilitado: {$afectados->count()}");

        return self::SUCCESS;
    }
}
