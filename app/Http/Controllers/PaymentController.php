<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentNotAllowedException;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /** El paciente presiona "Pagar": se crea la transacción y se va a Webpay. */
    public function start(Request $request, Booking $booking, PaymentService $payments): View|RedirectResponse
    {
        HoldController::authorizeVisitor($request, $booking);

        try {
            $redirect = $payments->start($booking, route('payment.return'));
        } catch (PaymentNotAllowedException $e) {
            return redirect()->route('hold.show', $booking->public_id)->with('payment_error', $e->getMessage());
        }

        return view('payment.redirect', ['redirect' => $redirect]);
    }

    /**
     * URL de retorno de Webpay (GET o POST). No usa sesión: Webpay puede volver
     * por POST desde otro sitio y la cookie del paciente no llegaría.
     */
    public function handleReturn(Request $request, PaymentService $payments): RedirectResponse
    {
        $value = function (string $key) use ($request): ?string {
            $input = $request->input($key);

            return is_string($input) && $input !== '' && strlen($input) <= 100 ? $input : null;
        };

        $payment = $payments->handleReturn(
            $value('token_ws'),
            $value('TBK_TOKEN'),
            $value('TBK_ORDEN_COMPRA'),
            $value('TBK_ID_SESION'),
        );

        abort_if($payment === null, 404);

        // La firma permite ver el resultado sin depender de la sesión.
        return redirect()->to(
            URL::temporarySignedRoute('payment.result', now()->addDay(), ['payment' => $payment->buy_order]),
            303,
        );
    }

    public function result(Payment $payment): View
    {
        $payment->load('booking.professional', 'booking.service');
        $booking = $payment->booking;
        $local = $booking->starts_at->copy()->setTimezone($booking->professional->timezone)->locale('es');

        return view('payment.result', [
            'payment' => $payment,
            'booking' => $booking,
            'dateLabel' => ucfirst($local->isoFormat('dddd D [de] MMMM [de] YYYY, HH:mm')),
            'paidAt' => $payment->transaction_date?->copy()
                ->setTimezone($booking->professional->timezone)->format('d-m-Y H:i'),
        ]);
    }
}
