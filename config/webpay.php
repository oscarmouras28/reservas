<?php

return [
    // integration (pruebas, no se cobra) | production
    'environment' => env('WEBPAY_ENVIRONMENT', 'integration'),

    // Solo para producción, y solo en el .env del servidor. En integración se
    // usan las credenciales públicas de Transbank que trae su SDK.
    'commerce_code' => env('WEBPAY_COMMERCE_CODE'),
    'api_key' => env('WEBPAY_API_KEY'),

    // Segundos máximos de espera por respuesta de Transbank.
    'timeout' => 20,

    // Minutos que se retiene la hora desde que se inicia un pago. Debe cubrir
    // la vida del token (5 min) más el formulario de Webpay (hasta 10 min).
    'hold_minutes' => 20,

    // Intentos de pago por reserva.
    'max_attempts' => 3,

    // Un pago del que no hubo retorno se concilia pasado este plazo.
    'reconcile_after_minutes' => 16,
];
