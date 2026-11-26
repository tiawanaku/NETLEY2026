<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Datos del proceso (penal): secretaria y otro funcionario del Ministerio Público. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscalias', function (Blueprint $table) {
            $table->string('secretaria', 150)->nullable()->after('telefono_fiscal');
            $table->string('otro_funcionario', 150)->nullable()->after('telefono_investigador');
        });
    }

    public function down(): void
    {
        Schema::table('fiscalias', function (Blueprint $table) {
            $table->dropColumn(['secretaria', 'otro_funcionario']);
        });
    }
};
