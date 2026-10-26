<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La consulta captura el mismo domicilio que luego pasa al cliente.
        Schema::table('consultas', function (Blueprint $table) {
            $table->string('zona', 100)->nullable()->after('direccion');
            $table->string('calles', 200)->nullable()->after('zona');
            $table->string('numero_domicilio', 50)->nullable()->after('calles');
            $table->string('indicaciones_domicilio', 500)->nullable()->after('numero_domicilio');
            $table->json('ubicacion')->nullable()->after('indicaciones_domicilio');
        });

        Schema::table('clientes', function (Blueprint $table) {
            // N° de cliente visible y correlativo (1, 2, 3…), independiente
            // del id interno, que puede saltar números.
            $table->unsignedInteger('nro_cliente')->nullable()->unique()->after('id');
            $table->string('pais', 40)->nullable()->after('direccion');
            $table->string('provincia', 40)->nullable()->after('pais');
            $table->string('ciudad', 40)->nullable()->after('provincia');
        });

        // Numera los clientes existentes en orden de alta.
        $n = 0;
        foreach (DB::table('clientes')->orderBy('id')->pluck('id') as $id) {
            DB::table('clientes')->where('id', $id)->update(['nro_cliente' => ++$n]);
        }
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropColumn(['zona', 'calles', 'numero_domicilio', 'indicaciones_domicilio', 'ubicacion']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['nro_cliente']);
            $table->dropColumn(['nro_cliente', 'pais', 'provincia', 'ciudad']);
        });
    }
};
