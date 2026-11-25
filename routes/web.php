<?php

use App\Http\Controllers\BookingPageController;
use Illuminate\Support\Facades\Route;

// La portada del subdominio no muestra nada propio: el sitio vive en psicologosahora.cl.
Route::redirect('/', 'https://psicologosahora.cl/');

// Reserva pública por psicólogo: reservas.psicologosahora.cl/{slug}
// El patrón excluye rutas reservadas del framework (/up, /storage).
$slug = '(?!up$|storage$)[a-z0-9]+(?:-[a-z0-9]+)*';

Route::middleware('throttle:60,1')->group(function () use ($slug) {
    Route::get('/{slug}', [BookingPageController::class, 'show'])->where('slug', $slug)->name('booking.show');
    Route::get('/{slug}/dias', [BookingPageController::class, 'days'])->where('slug', $slug)->name('booking.days');
    Route::get('/{slug}/horas', [BookingPageController::class, 'slots'])->where('slug', $slug)->name('booking.slots');
});
