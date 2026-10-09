<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('zona', 100)->nullable()->after('direccion');
            $table->string('calles', 200)->nullable()->after('zona');
            $table->string('numero_domicilio', 50)->nullable()->after('calles');
            $table->string('indicaciones_domicilio', 500)->nullable()->after('numero_domicilio');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['zona', 'calles', 'numero_domicilio', 'indicaciones_domicilio']);
        });
    }
};
