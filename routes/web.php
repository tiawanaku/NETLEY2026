<?php

use App\Filament\Admin\Support\ModoMenu;
use App\Http\Controllers\FrontpageController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\DocumentoController;
use App\Http\Controllers\Portal\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FrontpageController::class, 'index'])->name('frontpage');
Route::post('/consultas', [FrontpageController::class, 'storeConsulta'])->name('frontpage.consulta.store');
Route::post('/opiniones', [FrontpageController::class, 'storeTestimonio'])->name('frontpage.testimonio.store');

// Interruptor del menú lateral del panel admin (ver App\Filament\Admin\Support\ModoMenu):
// solo guarda la cookie y vuelve a la página de donde vino, para que el menú
// (armado del lado del servidor) se recalcule con el nuevo modo.
Route::get('/admin/menu-modo/{valor}', function (string $valor) {
    return back()->withCookie(cookie(
        ModoMenu::COOKIE,
        $valor === 'simple' ? '1' : '0',
        60 * 24 * 365,
    ));
})->middleware(['web', 'auth'])->whereIn('valor', ['simple', 'completo'])->name('admin.menu-modo.set');

Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:cliente')->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
    });

    Route::middleware('auth:cliente')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
        Route::post('/casos/{caso}/documentos', [DocumentoController::class, 'store'])->name('documentos.store');
        Route::get('/documentos/{documento}/descargar', [DocumentoController::class, 'descargar'])->name('documentos.descargar');
    });
});
