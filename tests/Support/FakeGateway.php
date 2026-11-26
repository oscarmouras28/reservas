<?php

namespace Tests\Support;

use App\Payments\GatewayRedirect;
use App\Payments\GatewayResult;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayException;

/** Pasarela falsa: las pruebas no salen a la red. */
class FakeGateway implements PaymentGateway
{
    public bool $createFails = false;

    public bool $commitFails = false;

    public bool $statusFails = false;

    public bool $refundSucceeds = true;

    /** Estado y código que responden commit() y status(). */
    public string $commitStatus = 'AUTHORIZED';

    public int $commitCode = 0;

    public string $status = 'AUTHORIZED';

    public ?int $statusCode = 0;

    /** Permite simular un monto distinto al cobrado. */
    public ?int $amountOverride = null;

    /** @var array<int, string> */
    public array $commits = [];

    /** @var array<int, string> */
    public array $refunds = [];

    /** @var array<string, array{buyOrder: string, amount: int}> */
    private array $created = [];

    public function create(string $buyOrder, string $sessionId, int $amount, string $returnUrl): GatewayRedirect
    {
        if ($this->createFails) {
            throw new PaymentGatewayException('sin conexión');
        }

        $token = 'token'.(count($this->created) + 1);
        $this->created[$token] = ['buyOrder' => $buyOrder, 'amount' => $amount];

        return new GatewayRedirect('https://webpay.test/pagar', $token);
    }

    public function commit(string $token): GatewayResult
    {
        $this->commits[] = $token;

        if ($this->commitFails) {
            throw new PaymentGatewayException('sin respuesta');
        }

        return $this->result($token, $this->commitStatus, $this->commitCode);
    }

    public function status(string $token): GatewayResult
    {
        if ($this->statusFails) {
            throw new PaymentGatewayException('sin respuesta');
        }

        return $this->result($token, $this->status, $this->statusCode);
    }

    public function refund(string $token, int $amount): bool
    {
        $this->refunds[] = $token;

        return $this->refundSucceeds;
    }

    private function result(string $token, string $status, ?int $code): GatewayResult
    {
        return new GatewayResult(
            status: $status,
            responseCode: $code,
            amount: $this->amountOverride ?? $this->created[$token]['amount'],
            buyOrder: $this->created[$token]['buyOrder'],
            authorizationCode: '1213',
            paymentTypeCode: 'VN',
            cardLast4: '6623',
            installments: 0,
        );
    }
}
