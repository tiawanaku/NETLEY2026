<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Documentos subidos junto con la cita, desde el formulario "Agendar". */
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->json('documentos')->nullable()->after('confirmada');
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropColumn('documentos');
        });
    }
};
