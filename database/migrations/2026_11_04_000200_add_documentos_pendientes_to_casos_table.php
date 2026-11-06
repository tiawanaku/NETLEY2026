<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca visible de "falta subir documento": true si el caso se creó
     * desde el asistente de Cliente Ejecutivo sin ningún documento en el
     * paso "Subir documentos" (ver ClienteCasoWizard::guardarDocumentos()).
     * Se apaga sola apenas se sube un documento ligado a este caso desde
     * la ficha del cliente (ver DocumentosRelationManager).
     */
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->boolean('documentos_pendientes')->default(false)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn('documentos_pendientes');
        });
    }
};
