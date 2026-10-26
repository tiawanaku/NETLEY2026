<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_cliente', function (Blueprint $table) {
            $table->string('tipo', 30)->nullable()->after('descripcion');
            // Cantidad (si tipo=fojas) u otro tipo escrito a mano (si tipo=otro).
            $table->string('tipo_detalle', 100)->nullable()->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_cliente', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'tipo_detalle']);
        });
    }
};
