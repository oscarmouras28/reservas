<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verificación en el servidor de Cloudflare Turnstile.
 * https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
 * El token dura 300 s y sirve una sola vez.
 */
class Turnstile
{
    private const URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(?string $token, ?string $ip = null): bool
    {
        $secret = config('reservas.turnstile.secret_key');

        // Sin clave configurada se rechaza: nunca se deja el formulario abierto.
        if (blank($secret)) {
            Log::error('Turnstile: falta TURNSTILE_SECRET_KEY en el .env');

            return false;
        }

        if (blank($token) || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::URL, array_filter([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (Throwable $e) {
            Log::warning('Turnstile: no se pudo verificar', ['error' => $e->getMessage()]);

            return false;
        }

        if ($response->json('success') !== true) {
            Log::info('Turnstile: token rechazado', ['errors' => $response->json('error-codes')]);

            return false;
        }

        return true;
    }
}
