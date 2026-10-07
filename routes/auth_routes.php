<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

/*
|--------------------------------------------------------------------------
| Rutas de Autenticación SEDATEZ (PostgreSQL etrib)
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'throttle:5,1'])->group(function () {
    Route::post('/api/login', [LoginController::class, 'login'])->name('api.login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});

Route::middleware(['web'])->group(function () {
    Route::get('/api/session', [LoginController::class, 'checkSession'])->name('api.session');
    Route::post('/api/logout', [LoginController::class, 'logout'])->name('api.logout');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
