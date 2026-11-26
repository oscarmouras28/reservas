<?php

namespace App\Http\Controllers;

use App\Exceptions\SlotUnavailableException;
use App\Http\Requests\StoreHoldRequest;
use App\Models\Booking;
use App\Models\Professional;
use App\Services\HoldService;
use App\Services\Turnstile;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HoldController extends Controller
{
    private const SESSION_KEY = 'holds';

    /** Recibe el formulario y retiene la hora. */
    public function store(StoreHoldRequest $request, string $slug, HoldService $holds, Turnstile $turnstile): JsonResponse
    {
        $professional = Professional::query()
            ->where('slug', $slug)
            ->where('active', true)
            ->with('availabilityRules')
            ->firstOrFail();

        $data = $request->validated();
        $service = $professional->services()->where('active', true)->findOrFail((int) $data['service']);

        // Se verifica después de validar los campos: el token sirve una sola vez.
        if (! $turnstile->verify($data['turnstile'] ?? null, $request->ip())) {
            throw ValidationException::withMessages([
                'turnstile' => 'No pudimos comprobar que no eres un robot. Inténtalo de nuevo.',
            ]);
        }

        try {
            $booking = $holds->create(
                $professional,
                $service,
                CarbonImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $data['start'], 'UTC'),
                [
                    'patient_name' => $data['name'],
                    'document_type' => $data['document_type'],
                    'document_number' => $data['document_number'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'address' => $data['address'],
                ],
                $request->session()->get(self::SESSION_KEY, []),
            );
        } catch (SlotUnavailableException) {
            return response()->json([
                'message' => 'Esa hora acaba de ocuparse. Elige otra.',
            ], 409);
        }

        // Solo este navegador puede ver y soltar sus retenciones.
        $ids = $request->session()->get(self::SESSION_KEY, []);
        $ids[] = $booking->public_id;
        $request->session()->put(self::SESSION_KEY, array_slice($ids, -5));

        return response()->json(['redirect' => route('hold.show', $booking->public_id)], 201);
    }

    /** Resumen de la hora retenida, con cuenta regresiva. */
    public function show(Request $request, Booking $booking): View
    {
        self::authorizeVisitor($request, $booking);
        $booking->load('professional', 'service');

        $local = $booking->starts_at->copy()->setTimezone($booking->professional->timezone)->locale('es');

        return view('booking.hold', [
            'booking' => $booking,
            'paymentInProgress' => $booking->hasPaymentInProgress(),
            'dateLabel' => ucfirst($local->isoFormat('dddd D [de] MMMM [de] YYYY, HH:mm')),
            'secondsLeft' => $booking->isHeld()
                ? (int) now()->diffInSeconds($booking->hold_expires_at)
                : 0,
        ]);
    }

    /** El paciente quiere cambiar de hora: suelta la retención. */
    public function release(Request $request, Booking $booking): RedirectResponse
    {
        self::authorizeVisitor($request, $booking);
        $booking->cancelHold();

        return redirect()->route('booking.show', $booking->professional->slug);
    }

    /** Corta con 404 si la retención no es de este navegador. */
    public static function authorizeVisitor(Request $request, Booking $booking): void
    {
        abort_unless(in_array($booking->public_id, $request->session()->get(self::SESSION_KEY, []), true), 404);
    }
}
