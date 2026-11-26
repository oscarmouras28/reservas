<?php

use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Schedule;

// Requiere el cron permanente "php artisan schedule:run" cada minuto.
Schedule::call(fn () => app(PaymentService::class)->reconcile())
    ->everyMinute()
    ->name('conciliar-pagos')
    ->withoutOverlapping(5);

Schedule::call(fn () => Booking::releaseExpiredHolds())
    ->everyMinute()
    ->name('liberar-retenciones')
    ->withoutOverlapping();

// Borra datos personales de reservas que nunca se completaron.
Schedule::call(fn () => Booking::purgeAbandonedData())
    ->dailyAt('04:00')
    ->name('purgar-datos-abandonados');
