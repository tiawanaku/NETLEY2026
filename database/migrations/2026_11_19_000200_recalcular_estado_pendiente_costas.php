<?php

use App\Enums\EstadoCaso;
use App\Models\Caso;
use Illuminate\Database\Migrations\Migration;

/**
 * "Pendiente" pasa a significar "cerrado como ganado, con patrocinio HiH, sin
 * costas y costos iniciadas" (ver Caso::actualizarEstadoCostas()): se
 * recalcula el estado de los casos ya cerrados con la regla nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Caso::query()
            ->whereIn('estado', [EstadoCaso::Cerrado->value, EstadoCaso::Pendiente->value])
            ->each(fn (Caso $caso) => $caso->actualizarEstadoCostas());
    }

    public function down(): void
    {
        // Sin vuelta atrás: el estado anterior se puede recalcular cerrando o
        // iniciando las costas otra vez.
    }
};
