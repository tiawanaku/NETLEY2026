<?php

namespace Database\Seeders;

use App\Models\Delito;
use Illuminate\Database\Seeder;

/**
 * Catálogo materia legal -> delito (ej. PENAL -> "Violación Art. 308.").
 * Los datos salen del sistema anterior (base netley-oficial: delitos
 * relacionados con materias_legales por materia_legal_id), con la materia
 * pasada al valor de App\Enums\Especialidad que usa delitos.area:
 * Familiar -> FAMILIA, Laboral -> LABORAL, Civil -> CIVIL, Penal -> PENAL.
 *
 * Se puede ejecutar varias veces: solo agrega los que falten.
 *   php artisan db:seed --class=DelitosSeeder
 */
class DelitosSeeder extends Seeder
{
    public function run(): void
    {
        $archivo = fopen(database_path('seeders/data/delitos.csv'), 'r');
        fgetcsv($archivo); // encabezado: area,delito

        $agregados = 0;

        while (($fila = fgetcsv($archivo)) !== false) {
            [$area, $delito] = $fila;

            $agregados += (int) Delito::query()->firstOrCreate(['area' => $area, 'delito' => $delito])->wasRecentlyCreated;
        }

        fclose($archivo);

        $this->command?->info("Delitos agregados: {$agregados}");
    }
}
