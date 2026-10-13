<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Denunciado" (persona o entidad denunciada, texto libre) y "Denuncias"
     * (delitos denunciados, varios del catálogo `delitos`) en la consulta y
     * en el caso. Se cargan al agendar una cita y en el paso "Proceso" del
     * wizard de Cliente Ejecutivo; al ascender la consulta pasan al caso.
     * Son adicionales al "Delito / Materia específica" (delito_id), que no
     * cambia.
     */
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->string('denunciado', 255)->nullable()->after('nota_interna');
        });

        Schema::table('casos', function (Blueprint $table) {
            $table->string('denunciado', 255)->nullable()->after('apersonamiento');
        });

        Schema::create('consulta_delito_denunciado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id')->constrained('consultas')->cascadeOnDelete();
            $table->foreignId('delito_id')->constrained('delitos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['consulta_id', 'delito_id']);
        });

        Schema::create('caso_delito_denunciado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->foreignId('delito_id')->constrained('delitos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['caso_id', 'delito_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caso_delito_denunciado');
        Schema::dropIfExists('consulta_delito_denunciado');

        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn('denunciado');
        });

        Schema::table('consultas', function (Blueprint $table) {
            $table->dropColumn('denunciado');
        });
    }
};
