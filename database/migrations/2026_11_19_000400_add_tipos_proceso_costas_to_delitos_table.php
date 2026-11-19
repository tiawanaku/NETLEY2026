<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipos de proceso del proceso de costas y costos (ver
 * ClienteCasoWizard::TIPOS_COSTAS y EditCliente::datosProcesoCostas()):
 * "Regularizar costas y costos" y "Resarcimiento de cobro costas y costos",
 * los dos en cada materia (el asistente de costas solo ofrece estos dos y
 * elige el que corresponde: Penal = resarcimiento, las demás = regularizar).
 */
return new class extends Migration
{
    public const AREAS = ['CIVIL', 'PENAL', 'FAMILIA', 'LABORAL'];

    public const TIPOS = ['Regularizar costas y costos', 'Resarcimiento de cobro costas y costos'];

    public function up(): void
    {
        foreach (self::AREAS as $area) {
            foreach (self::TIPOS as $delito) {
                if (! DB::table('delitos')->where('area', $area)->where('delito', $delito)->exists()) {
                    DB::table('delitos')->insert(['area' => $area, 'delito' => $delito, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('delitos')->whereIn('delito', self::TIPOS)
            ->whereNotExists(fn ($q) => $q->from('casos')->whereColumn('casos.delito_id', 'delitos.id'))
            ->delete();
    }
};
