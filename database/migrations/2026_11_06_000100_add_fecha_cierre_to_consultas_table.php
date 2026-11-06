<?php

use App\Enums\EstadoConsulta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->date('fecha_cierre')->nullable()->after('estado');
        });

        // Las que ya están cerradas no guardaron cuándo se cerraron: se usa
        // la última modificación como mejor aproximación.
        $abiertos = array_map(fn (EstadoConsulta $estado) => $estado->value, EstadoConsulta::abiertos());

        DB::table('consultas')
            ->whereNotIn('estado', $abiertos)
            ->update(['fecha_cierre' => DB::raw('DATE(updated_at)')]);
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropColumn('fecha_cierre');
        });
    }
};
