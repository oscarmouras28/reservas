<?php

namespace App\Payments;

/** Pasarela de pago. Hoy Webpay Plus; la interfaz permite cambiarla. */
interface PaymentGateway
{
    /** @throws PaymentGatewayException */
    public function create(string $buyOrder, string $sessionId, int $amount, string $returnUrl): GatewayRedirect;

    /** Confirma la transacción. @throws PaymentGatewayException */
    public function commit(string $token): GatewayResult;

    /** Consulta el estado sin confirmar. @throws PaymentGatewayException */
    public function status(string $token): GatewayResult;

    /** Devuelve el dinero. @throws PaymentGatewayException */
    public function refund(string $token, int $amount): bool;
}
