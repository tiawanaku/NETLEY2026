<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Solo afecta al guard `cliente` del portal público: el panel Filament
        // usa su propio middleware de autenticación y no pasa por aquí.
        $middleware->redirectGuestsTo(fn () => route('portal.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
