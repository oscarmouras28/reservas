<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Evita que alguien bloquee la agenda reteniendo muchas horas.
        RateLimiter::for('holds', fn (Request $request) => [
            Limit::perMinute(5)->by('min:'.$request->ip()),
            Limit::perHour(30)->by('hour:'.$request->ip()),
        ]);
    }
}
