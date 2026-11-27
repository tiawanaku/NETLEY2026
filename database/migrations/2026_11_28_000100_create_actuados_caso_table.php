<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Actuados del "Seguimiento del proceso": cada registro de seguimiento
 * planifica varios actuados (notificación, memorial, fotocopias…, con
 * fecha, hora y alarma) y el "Informe" los marca como concluidos. Uno no
 * concluido sigue pendiente (pasa al día siguiente) hasta concluirlo o
 * eliminarlo.
 *
 * Los "¿Qué sigue?" anteriores (seguimientos_caso.proxima_*) pasan a ser
 * actuados, concluidos si ya estaban marcados como cumplidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actuados_caso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            // Registro de seguimiento que lo planificó.
            $table->foreignId('seguimiento_id')->nullable()->constrained('seguimientos_caso')->cascadeOnDelete();
            // Informe en el que se marcó como concluido.
            $table->foreignId('informe_id')->nullable()->constrained('seguimientos_caso')->nullOnDelete();
            $table->string('tipo', 60);
            $table->text('detalle')->nullable();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->boolean('alarma')->default(false);
            $table->boolean('concluido')->default(false);
            $table->timestamp('concluido_at')->nullable();
            $table->timestamps();

            $table->index(['caso_id', 'concluido']);
        });

        Schema::table('seguimientos_caso', function (Blueprint $table) {
            // "seguimiento" (actuados a realizar) o "informe" (actuados realizados).
            $table->string('tipo_registro', 20)->default('seguimiento')->after('personal_id');
        });

        DB::table('seguimientos_caso')->whereNotNull('proxima_fecha')->orderBy('id')->each(function ($s) {
            DB::table('actuados_caso')->insert([
                'caso_id' => $s->caso_id,
                'seguimiento_id' => $s->id,
                'tipo' => 'Otro',
                'detalle' => $s->proxima_actuacion,
                'fecha' => $s->proxima_fecha,
                'alarma' => true,
                'concluido' => $s->proxima_cumplida_at !== null,
                'concluido_at' => $s->proxima_cumplida_at,
                'created_at' => $s->created_at ?? now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actuados_caso');

        Schema::table('seguimientos_caso', function (Blueprint $table) {
            $table->dropColumn('tipo_registro');
        });
    }
};
