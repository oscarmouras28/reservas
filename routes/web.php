<?php

use App\Http\Controllers\BookingPageController;
use App\Http\Controllers\HoldController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

// La portada del subdominio no muestra nada propio: el sitio vive en psicologosahora.cl.
Route::redirect('/', 'https://psicologosahora.cl/');

// Reserva pública por psicólogo: reservas.psicologosahora.cl/{slug}
// El patrón excluye rutas reservadas (/up, /storage, /reserva, /pago).
$slug = '(?!up$|storage$|reserva$|pago$)[a-z0-9]+(?:-[a-z0-9]+)*';

Route::middleware('throttle:60,1')->group(function () use ($slug) {
    // Hora retenida de este visitante.
    Route::get('/reserva/{booking:public_id}', [HoldController::class, 'show'])->name('hold.show');
    Route::post('/reserva/{booking:public_id}/liberar', [HoldController::class, 'release'])->name('hold.release');

    Route::get('/{slug}', [BookingPageController::class, 'show'])->where('slug', $slug)->name('booking.show');
    Route::get('/{slug}/dias', [BookingPageController::class, 'days'])->where('slug', $slug)->name('booking.days');
    Route::get('/{slug}/horas', [BookingPageController::class, 'slots'])->where('slug', $slug)->name('booking.slots');
});

// Pago: inicio (solo el navegador dueño de la retención).
Route::post('/reserva/{booking:public_id}/pagar', [PaymentController::class, 'start'])
    ->middleware('throttle:payments')
    ->name('payment.start');

// Retorno desde Webpay: sin sesión, cookies ni CSRF (llega desde otro sitio, por GET o POST).
Route::match(['get', 'post'], '/pago/retorno', [PaymentController::class, 'handleReturn'])
    ->withoutMiddleware('web')
    ->middleware('throttle:60,1')
    ->name('payment.return');

Route::get('/pago/resultado/{payment:buy_order}', [PaymentController::class, 'result'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('payment.result');

// Envío del formulario: límite propio, más estricto (ver AppServiceProvider).
Route::post('/{slug}/reservar', [HoldController::class, 'store'])
    ->where('slug', $slug)
    ->middleware('throttle:holds')
    ->name('hold.store');
