<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Materia legal y delito de la consulta misma (no de una respuesta):
     * ahora se piden al crearla, en vez de solo al registrar una respuesta.
     * Mismos nombres de columna que respuestas.designacion/delito_id, para
     * poder reusar RespuestaFields::camposMateria() tal cual.
     */
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->string('designacion', 20)->nullable()->after('consulta'); // App\Enums\Especialidad
            $table->string('materia_texto', 120)->nullable()->after('designacion');
            $table->foreignId('delito_id')->nullable()->after('materia_texto')->constrained('delitos')->nullOnDelete();
            $table->string('delito_texto', 255)->nullable()->after('delito_id');
        });
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delito_id');
            $table->dropColumn(['designacion', 'materia_texto', 'delito_texto']);
        });
    }
};
