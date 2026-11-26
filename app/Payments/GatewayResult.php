<?php

namespace App\Payments;

use Carbon\CarbonImmutable;

/** Respuesta de la pasarela al confirmar o consultar una transacción. */
final class GatewayResult
{
    public function __construct(
        public readonly ?string $status,
        public readonly ?int $responseCode,
        public readonly ?int $amount = null,
        public readonly ?string $buyOrder = null,
        public readonly ?string $authorizationCode = null,
        public readonly ?string $paymentTypeCode = null,
        public readonly ?string $cardLast4 = null,
        public readonly ?int $installments = null,
        public readonly ?CarbonImmutable $transactionDate = null,
    ) {}

    /** Regla de Transbank: response_code exactamente 0 y estado AUTHORIZED. */
    public function isApproved(): bool
    {
        return $this->responseCode === 0 && $this->status === 'AUTHORIZED';
    }

    /** La transacción existe pero no se ha pagado. */
    public function isUnpaid(): bool
    {
        return $this->status === 'INITIALIZED';
    }
}
