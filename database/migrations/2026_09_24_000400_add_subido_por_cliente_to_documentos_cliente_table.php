<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_cliente', function (Blueprint $table) {
            $table->boolean('subido_por_cliente')->default(false)->after('tipo_detalle');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_cliente', function (Blueprint $table) {
            $table->dropColumn('subido_por_cliente');
        });
    }
};
