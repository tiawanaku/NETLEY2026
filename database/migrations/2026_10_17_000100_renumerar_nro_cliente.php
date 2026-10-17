<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Varios clientes se cargaron sin "nro_cliente" (quedó nulo) y uno solo
     * -el último en pasar por el alta de la app- se quedó con el valor "1"
     * (lo asigna el modelo al crear, tomando el máximo existente + 1, que en
     * ese momento era nulo). Se renumeran TODOS en orden de alta (id), igual
     * que hizo la migración original que creó la columna.
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('clientes')->update(['nro_cliente' => null]);

            $n = 0;
            foreach (DB::table('clientes')->orderBy('id')->pluck('id') as $id) {
                DB::table('clientes')->where('id', $id)->update(['nro_cliente' => ++$n]);
            }
        });
    }

    public function down(): void
    {
        // No se revierte: no hay forma de recuperar la numeración previa
        // (incompleta/incorrecta) ni tendría sentido hacerlo.
    }
};
