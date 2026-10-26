<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->unsignedSmallInteger('duracion_meses')->nullable()->after('fecha_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn('duracion_meses');
        });
    }
};
