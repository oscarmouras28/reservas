<?php

return [
    // Zona horaria por defecto de los profesionales (se guarda también por profesional).
    'timezone' => env('RESERVAS_TIMEZONE', 'America/Santiago'),

    // Anticipación mínima para reservar una hora (no se ofrecen horas más cercanas).
    'min_notice_hours' => (int) env('RESERVAS_MIN_NOTICE_HOURS', 12),

    // Hasta cuántos días hacia adelante se ofrece disponibilidad.
    'max_advance_days' => (int) env('RESERVAS_MAX_ADVANCE_DAYS', 45),

    // Minutos que se retiene una hora mientras el paciente paga (Etapa 1b/1c).
    'hold_minutes' => (int) env('RESERVAS_HOLD_MINUTES', 10),
];
