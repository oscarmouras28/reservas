<?php

namespace App\Payments;

/** Datos para enviar al paciente al formulario de pago. */
final class GatewayRedirect
{
    public function __construct(
        public readonly string $url,
        public readonly string $token,
    ) {}
}
