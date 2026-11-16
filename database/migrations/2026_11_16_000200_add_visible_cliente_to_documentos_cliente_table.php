<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos del caso: siempre los ve Netley, y el cliente solo los que se
 * marquen "Cliente" (casillero al subirlos, ver Casos\DocumentosRelationManager)
 * — esos son los que aparecen en su portal.
 *
 * Los que ya existían seguían viéndose en el portal: se dejan visibles para
 * no ocultarle nada al cliente de golpe (se puede desmarcar con "Editar").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_cliente', function (Blueprint $table) {
            $table->boolean('visible_cliente')->default(false)->after('subido_por_cliente');
        });

        DB::table('documentos_cliente')->update(['visible_cliente' => true]);
    }

    public function down(): void
    {
        Schema::table('documentos_cliente', function (Blueprint $table) {
            $table->dropColumn('visible_cliente');
        });
    }
};
