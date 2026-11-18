<?php

use App\Http\Controllers\DiagController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Diagnóstico temporal de la Etapa 0 (solo con DIAG_ENABLED=true). Eliminar al terminar.
if (config('diag.enabled')) {
    Route::prefix('_diag')->middleware('throttle:20,1')->group(function () {
        Route::get('/ping', [DiagController::class, 'ping']);
        Route::post('/webhook', [DiagController::class, 'webhook']);
        Route::get('/back', [DiagController::class, 'back']);
    });
}
