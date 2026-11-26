<?php

namespace App\Services;

use App\Exceptions\PaymentNotAllowedException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Professional;
use App\Payments\GatewayRedirect;
use App\Payments\GatewayResult;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayException;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pago de una reserva.
 *
 * Reglas: una reserva solo se confirma con una respuesta aprobada de la
 * pasarela; nunca por el hecho de volver al sitio. Las llamadas a la pasarela
 * se hacen fuera de las transacciones, para no dejar la agenda bloqueada.
 */
class PaymentService
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * Crea un intento de pago y extiende la retención de la hora.
     *
     * @throws PaymentNotAllowedException
     */
    public function start(Booking $booking, string $returnUrl): GatewayRedirect
    {
        $payment = $this->locked($booking, function (Booking $booking) {
            if (! $booking->isHeld()) {
                throw new PaymentNotAllowedException('La reserva venció. Elige la hora de nuevo.');
            }

            // Cuentan los intentos que llegaron a Webpay.
            if ($booking->payments()->whereNotNull('token')->count() >= (int) config('webpay.max_attempts')) {
                throw new PaymentNotAllowedException('Alcanzaste el máximo de intentos de pago para esta reserva.');
            }

            $this->extendHold($booking);

            return $booking->payments()->create([
                'environment' => config('webpay.environment'),
                'buy_order' => (string) Str::ulid(),
                'amount' => $booking->price,
                'status' => Payment::CREATED,
            ]);
        });

        try {
            $redirect = $this->gateway->create($payment->buy_order, $booking->public_id, $payment->amount, $returnUrl);
        } catch (PaymentGatewayException $e) {
            report($e);
            $payment->update(['status' => Payment::ERROR]);

            throw new PaymentNotAllowedException('No pudimos conectar con Webpay. Inténtalo de nuevo en un momento.');
        }

        $payment->update(['token' => $redirect->token]);

        return $redirect;
    }

    /**
     * Procesa lo que Webpay envía a la URL de retorno.
     *
     *  - solo token_ws: terminó el pago (aprobado o rechazado) y se confirma
     *  - TBK_TOKEN: el paciente anuló
     *  - token_ws y TBK_TOKEN: error en el formulario
     *  - solo orden y sesión: se agotó el tiempo
     */
    public function handleReturn(?string $token, ?string $abortToken, ?string $buyOrder, ?string $sessionId): ?Payment
    {
        if ($abortToken !== null) {
            $payment = $this->findByToken($abortToken) ?? ($token !== null ? $this->findByToken($token) : null);

            return $payment ? $this->close($payment, $token === null ? Payment::ABORTED : Payment::ERROR) : null;
        }

        if ($token !== null) {
            $payment = $this->findByToken($token);

            return $payment ? $this->confirm($payment) : null;
        }

        if ($buyOrder !== null) {
            $payment = Payment::query()->where('buy_order', $buyOrder)->with('booking')->first();

            if (! $payment || $payment->booking->public_id !== $sessionId) {
                return null;
            }

            return $this->close($payment, Payment::TIMEOUT);
        }

        return null;
    }

    /**
     * Resuelve los pagos que quedaron sin terminar (el paciente no volvió, o no
     * hubo respuesta al confirmar). Se ejecuta desde el cron.
     *
     * @return int cantidad de pagos revisados
     */
    public function reconcile(): int
    {
        $abandonedBefore = now()->subMinutes((int) config('webpay.reconcile_after_minutes'));

        $payments = Payment::query()
            ->where(function ($q) use ($abandonedBefore) {
                $q->where(fn ($q) => $q->where('status', Payment::CREATED)->where('created_at', '<=', $abandonedBefore))
                    ->orWhere(fn ($q) => $q->whereIn('status', [Payment::UNKNOWN, Payment::COMMITTING])
                        ->where('updated_at', '<=', now()->subMinute()));
            })
            ->orderBy('id')
            ->limit(20)
            ->get();

        foreach ($payments as $payment) {
            $this->reconcileOne($payment);
        }

        return $payments->count();
    }

    private function reconcileOne(Payment $payment): void
    {
        // Nunca llegó a Webpay.
        if ($payment->token === null) {
            $this->close($payment, Payment::ERROR);

            return;
        }

        try {
            $result = $this->gateway->status($payment->token);
        } catch (PaymentGatewayException $e) {
            report($e);

            // Transbank permite consultar solo durante 7 días.
            if ($payment->created_at->lt(now()->subDays(7))) {
                $this->flagForReview($payment, 'sin respuesta de estado en 7 días');
            }

            return;
        }

        if ($result->isUnpaid()) {
            $this->finish($payment, Payment::ABANDONED, $result);

            return;
        }

        if (! $result->isApproved()) {
            $this->finish($payment, Payment::REJECTED, $result);

            return;
        }

        // Figura aprobado: solo vale lo que responda la confirmación.
        if ($payment->status === Payment::CREATED) {
            $this->confirm($payment);

            return;
        }

        try {
            $this->apply($payment, $this->gateway->commit($payment->token));
        } catch (PaymentGatewayException $e) {
            report($e);

            if ($payment->created_at->lt(now()->subHour())) {
                $this->flagForReview($payment, 'figura aprobado pero no se pudo confirmar');
            }
        }
    }

    private function confirm(Payment $payment): Payment
    {
        // Paso 1: apartar el pago, solo una vez y solo si la hora sigue siendo suya.
        $claimed = $this->locked($payment->booking, function (Booking $booking) use ($payment) {
            $fresh = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if ($fresh->status !== Payment::CREATED) {
                return false; // ya resuelto: recargar la página no repite el commit
            }

            // Ya pagada con otro intento, o la hora la tomó otra persona.
            if ($booking->status === Booking::CONFIRMED || ! $this->reclaimSlot($booking)) {
                $fresh->update(['status' => Payment::SLOT_LOST]);

                return false;
            }

            $fresh->update(['status' => Payment::COMMITTING]);

            return true;
        });

        if (! $claimed) {
            $payment->refresh();

            if ($payment->status === Payment::SLOT_LOST) {
                $this->refundIfCharged($payment);
            }

            return $payment->refresh();
        }

        // Paso 2: confirmar en la pasarela.
        try {
            $result = $this->gateway->commit($payment->token);
        } catch (PaymentGatewayException $e) {
            report($e);
            Payment::query()->whereKey($payment->id)->where('status', Payment::COMMITTING)
                ->update(['status' => Payment::UNKNOWN, 'updated_at' => now()]);

            return $payment->refresh();
        }

        // Paso 3: aplicar la respuesta.
        $this->apply($payment, $result);

        return $payment->refresh();
    }

    private function apply(Payment $payment, GatewayResult $result): void
    {
        if (! $result->isApproved()) {
            $this->finish($payment, Payment::REJECTED, $result);

            return;
        }

        if ($result->amount !== $payment->amount || $result->buyOrder !== $payment->buy_order) {
            $this->finish($payment, Payment::REVIEW, $result);
            Log::critical('Pago en revisión: monto u orden no coinciden.', ['payment' => $payment->id]);

            return;
        }

        try {
            $confirmed = $this->locked($payment->booking, function (Booking $booking) use ($payment, $result) {
                $fresh = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

                if (! in_array($fresh->status, Payment::IN_PROGRESS, true)) {
                    return true; // otro proceso ya lo aplicó
                }

                if ($booking->status === Booking::CONFIRMED || ! $this->reclaimSlot($booking)) {
                    return false;
                }

                $booking->forceFill([
                    'status' => Booking::CONFIRMED,
                    'confirmed_at' => now(),
                    'hold_expires_at' => null,
                ])->save();

                $fresh->update($this->details($result) + ['status' => Payment::APPROVED, 'committed_at' => now()]);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            $confirmed = false;
        }

        // Cobrado pero sin hora: se devuelve el dinero.
        if (! $confirmed) {
            $this->finish($payment, Payment::SLOT_LOST, $result);
            $this->refundIfCharged($payment->refresh());
        }
    }

    private function refundIfCharged(Payment $payment): void
    {
        if ($payment->token === null) {
            return;
        }

        try {
            if (! $this->gateway->status($payment->token)->isApproved()) {
                return;
            }

            if ($this->gateway->refund($payment->token, $payment->amount)) {
                $payment->update(['status' => Payment::REFUNDED]);
                Log::warning('Pago devuelto: la hora ya no estaba disponible.', ['payment' => $payment->id]);

                return;
            }
        } catch (PaymentGatewayException $e) {
            report($e);
        }

        $this->flagForReview($payment, 'cobrado sin hora y sin devolución automática');
    }

    /** Cierra un pago que seguía esperando respuesta, sin tocar los ya resueltos. */
    private function close(Payment $payment, string $status): Payment
    {
        Payment::query()
            ->whereKey($payment->id)
            ->where('status', Payment::CREATED)
            ->update(['status' => $status, 'updated_at' => now()]);

        return $payment->refresh();
    }

    private function finish(Payment $payment, string $status, GatewayResult $result): void
    {
        Payment::query()
            ->whereKey($payment->id)
            ->whereIn('status', Payment::IN_PROGRESS)
            ->first()
            ?->update($this->details($result) + ['status' => $status]);

        $payment->refresh();
    }

    private function flagForReview(Payment $payment, string $reason): void
    {
        $payment->update(['status' => Payment::REVIEW]);
        Log::critical('Pago en revisión: '.$reason.'.', ['payment' => $payment->id, 'orden' => $payment->buy_order]);
    }

    /** @return array<string, mixed> */
    private function details(GatewayResult $result): array
    {
        return [
            'response_code' => $result->responseCode,
            'authorization_code' => $result->authorizationCode,
            'payment_type_code' => $result->paymentTypeCode,
            'card_last4' => $result->cardLast4,
            'installments' => $result->installments,
            'transaction_date' => $result->transactionDate,
        ];
    }

    /**
     * Asegura que la hora siga siendo de esta reserva; si la retención venció y
     * nadie la tomó, la recupera. Debe llamarse dentro de locked().
     */
    private function reclaimSlot(Booking $booking): bool
    {
        if (! in_array($booking->status, [Booking::PENDING, Booking::EXPIRED], true)) {
            return false;
        }

        if (! $booking->isHeld()) {
            $taken = Booking::query()
                ->where('professional_id', $booking->professional_id)
                ->whereKeyNot($booking->id)
                ->occupying()
                ->where('starts_at', '<', $booking->ends_at)
                ->where('ends_at', '>', $booking->starts_at)
                ->exists();

            if ($taken) {
                return false;
            }
        }

        $booking->forceFill(['status' => Booking::PENDING, 'active_start' => $booking->starts_at]);
        $this->extendHold($booking);

        return true;
    }

    private function extendHold(Booking $booking): void
    {
        $until = now()->addMinutes((int) config('webpay.hold_minutes'));

        if ($booking->hold_expires_at === null || $booking->hold_expires_at->lt($until)) {
            $booking->hold_expires_at = $until;
        }

        $booking->save();
    }

    private function findByToken(string $token): ?Payment
    {
        return Payment::query()->where('token', $token)->with('booking')->first();
    }

    /**
     * Ejecuta en una transacción con el mismo candado por profesional que usa
     * HoldService, y entrega la reserva recién leída.
     */
    private function locked(Booking $booking, Closure $callback): mixed
    {
        return DB::transaction(function () use ($booking, $callback) {
            Professional::query()->whereKey($booking->professional_id)->lockForUpdate()->first();
            Booking::releaseExpiredHolds();

            return $callback(Booking::query()->whereKey($booking->id)->lockForUpdate()->first());
        }, 3);
    }
}
