<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Fecha de registro" (editable, por defecto hoy) y "Atendido por"
     * (quién registró la consulta, de la lista de personal habilitado) del
     * menú de Agendar. `atendido_por` (string) nunca se usó desde ningún
     * formulario; se reemplaza por una relación real a `personal`.
     */
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropColumn('atendido_por');
        });

        Schema::table('consultas', function (Blueprint $table) {
            $table->date('fecha_registro')->nullable()->after('forma_ingreso');
            $table->foreignId('atendido_por_id')->nullable()->after('fecha_registro')->constrained('personal')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atendido_por_id');
            $table->dropColumn('fecha_registro');
        });

        Schema::table('consultas', function (Blueprint $table) {
            $table->string('atendido_por', 100)->nullable()->after('forma_ingreso');
        });
    }
};
