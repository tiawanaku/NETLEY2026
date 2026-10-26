<?php

/**
 * Borra los registros que crean las pruebas QA de navegador (tests/e2e).
 * Solo toca datos marcados como QA: correo @qa-e2e.test o nombres "Qaprueba".
 *
 * Uso: php tests/e2e/limpiar-datos-qa.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$esQa = fn ($query) => $query->where('correo', 'like', '%@qa-e2e.test')->orWhere('nombres', 'like', 'Qaprueba%');

DB::transaction(function () use ($esQa) {
    $consultas = DB::table('consultas')->where($esQa)->pluck('id');
    $clientes = DB::table('clientes')->where($esQa)->pluck('id');
    $casos = DB::table('casos')->whereIn('cliente_id', $clientes)->pluck('id');
    $personal = DB::table('personal')->where($esQa)->pluck('id');

    // Citas y llamadas no se borran en cascada (nullOnDelete): se eliminan antes.
    $citas = DB::table('citas')->whereIn('consulta_id', $consultas)->orWhereIn('caso_id', $casos)->delete();
    DB::table('llamadas')->whereIn('consulta_id', $consultas)->delete();

    $resumen = [
        'citas' => $citas,
        'consultas' => DB::table('consultas')->whereIn('id', $consultas)->delete(),
        // Casos, pagos, planes de pago y relaciones caen en cascada con el cliente.
        'clientes' => DB::table('clientes')->whereIn('id', $clientes)->delete(),
        'personal' => DB::table('personal')->whereIn('id', $personal)->delete(),
    ];

    echo 'Datos QA eliminados: '.json_encode($resumen).PHP_EOL;
});
