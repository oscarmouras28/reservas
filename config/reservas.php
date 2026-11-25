<?php

return [
    // Zona horaria por defecto de los profesionales (se guarda también por profesional).
    'timezone' => env('RESERVAS_TIMEZONE', 'America/Santiago'),

    // Anticipación mínima para reservar una hora (no se ofrecen horas más cercanas).
    'min_notice_hours' => (int) env('RESERVAS_MIN_NOTICE_HOURS', 12),

    // Hasta cuántos días hacia adelante se ofrece disponibilidad.
    'max_advance_days' => (int) env('RESERVAS_MAX_ADVANCE_DAYS', 45),

    // Minutos que se retiene una hora mientras el paciente paga.
    'hold_minutes' => (int) env('RESERVAS_HOLD_MINUTES', 10),

    // Días que se conservan los datos de reservas que nunca se completaron.
    'abandoned_data_days' => (int) env('RESERVAS_ABANDONED_DATA_DAYS', 7),

    // Consentimiento: si cambia el texto o la política, subir la versión.
    'privacy_url' => 'https://somosbienestar.cl/politica-de-privacidad/',
    'consent_version' => '2026-11-26',
    'consent_text' => 'Acepto la política de privacidad (https://somosbienestar.cl/politica-de-privacidad/). '
        .'Declaro ser mayor de 18 años.',

    // Cloudflare Turnstile (anti-bots). Las claves van solo en el .env.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],
];
