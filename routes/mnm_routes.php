<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\MnmDashboardController;

/*
|--------------------------------------------------------------------------
| Rutas del Dashboard GIS de Contribuyentes MNM (SEDATEZ)
|--------------------------------------------------------------------------
*/

Route::middleware(['web'])->prefix('api/mnm')->group(function () {
    Route::get('/empresas', [MnmDashboardController::class, 'getEmpresas'])->name('api.mnm.empresas');
    Route::get('/kpis', [MnmDashboardController::class, 'getKpis'])->name('api.mnm.kpis');
    Route::post('/coordenadas', [MnmDashboardController::class, 'actualizarCoordenadas'])->name('api.mnm.coordenadas');
});
