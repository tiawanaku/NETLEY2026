<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recibos anulados (Contabilidad › Recibos anulados): al corregir un pago
 * ("Editar pago"), el recibo original queda anulado — con fecha, motivo y
 * quién lo anuló — y se emite uno nuevo con otro número, enlazado aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->boolean('anulado')->default(false)->after('registrado_por');
            $table->timestamp('anulado_at')->nullable()->after('anulado');
            $table->text('motivo_anulacion')->nullable()->after('anulado_at');
            $table->string('anulado_por')->nullable()->after('motivo_anulacion');
            $table->foreignId('reemplazado_por_id')->nullable()->after('anulado_por')->constrained('pagos')->nullOnDelete();
            $table->index('anulado');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reemplazado_por_id');
            $table->dropIndex(['anulado']);
            $table->dropColumn(['anulado', 'anulado_at', 'motivo_anulacion', 'anulado_por']);
        });
    }
};
