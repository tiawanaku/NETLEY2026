<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Casos eliminados" (menú lateral): eliminar un caso ya no lo borra de la
 * base, queda marcado como eliminado y se puede restaurar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
