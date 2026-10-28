<?php

use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\Finance\CategoriaController;
use App\Http\Controllers\Finance\CuentaController;
use App\Http\Controllers\Finance\DashboardController;
use App\Http\Controllers\Finance\MovimientoController;
use App\Http\Controllers\Finance\PresupuestoController;
use App\Http\Controllers\Finance\TransferenciaController;
use Illuminate\Support\Facades\Route;

Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:tokens')->name('api.tokens.store');
Route::middleware(['auth:sanctum', 'throttle:120,1'])->name('api.')->group(function () {
    Route::delete('/tokens/actual', [TokenController::class, 'destroy'])->name('tokens.destroy');
    Route::middleware('abilities:finanzas')->group(function () {
        Route::apiResource('transferencias', TransferenciaController::class)->only(['index', 'store', 'show'])->parameters(['transferencias' => 'id']);
        Route::apiResource('presupuestos', PresupuestoController::class)->parameters(['presupuestos' => 'id']);
        Route::get('/tipo-cambio', ExchangeRateController::class)->name('tipo-cambio');
        Route::apiResource('cuentas', CuentaController::class)->parameters(['cuentas' => 'id']);
        Route::apiResource('categorias', CategoriaController::class)->parameters(['categorias' => 'id']);
        Route::apiResource('movimientos', MovimientoController::class)->parameters(['movimientos' => 'id']);
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });
});
