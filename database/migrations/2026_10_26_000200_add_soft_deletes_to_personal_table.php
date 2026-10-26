<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite "eliminar" personal sin borrar el registro de verdad (queda
     * disponible en la subsección "Eliminados" del menú lateral y se puede
     * restaurar).
     */
    public function up(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
