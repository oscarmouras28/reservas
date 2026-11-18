<?php

/*
 * Rutas de diagnóstico TEMPORALES para la Etapa 0 (WAF y correo).
 * Se activan solo con DIAG_ENABLED=true en el .env del servidor y deben
 * eliminarse (o dejarse en false) al terminar las pruebas.
 */
return [
    'enabled' => (bool) env('DIAG_ENABLED', false),
];
