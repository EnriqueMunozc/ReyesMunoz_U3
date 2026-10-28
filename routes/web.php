<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\Finance\CategoriaController;
use App\Http\Controllers\Finance\CuentaController;
use App\Http\Controllers\Finance\DashboardController;
use App\Http\Controllers\Finance\MovimientoController;
use Illuminate\Support\Facades\Route;

Route::pattern('id', '[0-9]+');

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/registro', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/registro', [RegisteredUserController::class, 'store']);
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/conversor', ExchangeRateController::class)->name('conversor');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('cuentas', CuentaController::class)->parameters(['cuentas' => 'id']);
    Route::resource('categorias', CategoriaController::class)->parameters(['categorias' => 'id']);
    Route::resource('movimientos', MovimientoController::class)->parameters(['movimientos' => 'id']);
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
