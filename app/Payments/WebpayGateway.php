<?php

namespace App\Payments;

use Carbon\CarbonImmutable;
use Throwable;
use Transbank\Webpay\Options;
use Transbank\Webpay\WebpayPlus;
use Transbank\Webpay\WebpayPlus\Responses\TransactionStatusResponse;
use Transbank\Webpay\WebpayPlus\Transaction;

/** Webpay Plus mediante el SDK oficial de Transbank. */
class WebpayGateway implements PaymentGateway
{
    public function create(string $buyOrder, string $sessionId, int $amount, string $returnUrl): GatewayRedirect
    {
        $response = $this->call(fn (Transaction $t) => $t->create($buyOrder, $sessionId, $amount, $returnUrl));

        if (! $response->getUrl() || ! $response->getToken()) {
            throw new PaymentGatewayException('Webpay no entregó token o URL.');
        }

        return new GatewayRedirect($response->getUrl(), $response->getToken());
    }

    public function commit(string $token): GatewayResult
    {
        return $this->result($this->call(fn (Transaction $t) => $t->commit($token)));
    }

    public function status(string $token): GatewayResult
    {
        return $this->result($this->call(fn (Transaction $t) => $t->status($token)));
    }

    public function refund(string $token, int $amount): bool
    {
        return $this->call(fn (Transaction $t) => $t->refund($token, $amount))->success();
    }

    private function call(callable $operation): mixed
    {
        $transaction = new Transaction($this->options());

        try {
            return $operation($transaction);
        } catch (Throwable $e) {
            throw new PaymentGatewayException('Error de Webpay: '.$e->getMessage(), 0, $e);
        }
    }

    private function options(): Options
    {
        $environment = config('webpay.environment');
        $timeout = (int) config('webpay.timeout');

        if ($environment === 'integration') {
            return new Options(
                WebpayPlus::INTEGRATION_API_KEY,
                WebpayPlus::INTEGRATION_COMMERCE_CODE,
                Options::ENVIRONMENT_INTEGRATION,
                $timeout,
            );
        }

        $code = (string) config('webpay.commerce_code');
        $key = (string) config('webpay.api_key');

        // Falla cerrado: sin credenciales propias no se intenta cobrar.
        if ($environment !== 'production' || $code === '' || $key === '') {
            throw new PaymentGatewayException('Configuración de Webpay incompleta.');
        }

        return new Options($key, $code, Options::ENVIRONMENT_PRODUCTION, $timeout);
    }

    private function result(TransactionStatusResponse $response): GatewayResult
    {
        $amount = $response->getAmount();
        $card = $response->getCardNumber();
        $date = $response->getTransactionDate();

        return new GatewayResult(
            status: $response->getStatus(),
            responseCode: $response->getResponseCode(),
            amount: $amount === null ? null : (int) $amount,
            buyOrder: $response->getBuyOrder(),
            authorizationCode: $response->getAuthorizationCode(),
            paymentTypeCode: $response->getPaymentTypeCode(),
            cardLast4: $card === null ? null : substr($card, -4),
            installments: $response->getInstallmentsNumber(),
            transactionDate: $date ? CarbonImmutable::parse($date)->utc() : null,
        );
    }
}
