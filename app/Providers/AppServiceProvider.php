<?php

namespace App\Providers;

use App\Payments\PaymentGateway;
use App\Payments\WebpayGateway;
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
        $this->app->bind(PaymentGateway::class, WebpayGateway::class);
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

        RateLimiter::for('payments', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
    }
}
