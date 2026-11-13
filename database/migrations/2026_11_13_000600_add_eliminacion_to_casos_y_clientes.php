<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Al eliminar un caso o un cliente se pide la fecha, el motivo y un documento de
 * respaldo de la eliminación (ver App\Filament\Admin\Support\Eliminacion).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['casos', 'clientes'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->date('fecha_eliminacion')->nullable()->after('deleted_at');
                $table->text('motivo_eliminacion')->nullable()->after('fecha_eliminacion');
                $table->string('documento_eliminacion')->nullable()->after('motivo_eliminacion');
            });
        }
    }

    public function down(): void
    {
        foreach (['casos', 'clientes'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn(['fecha_eliminacion', 'motivo_eliminacion', 'documento_eliminacion']);
            });
        }
    }
};
