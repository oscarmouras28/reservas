@extends('layouts.app')

@php
    use App\Models\Payment;

    [$title, $message] = match ($payment->status) {
        Payment::APPROVED => ['Pago aprobado', 'Tu hora quedó reservada.'],
        Payment::REJECTED => ['Pago rechazado', 'No se realizó el cobro. Puedes intentarlo de nuevo con otro medio de pago.'],
        Payment::ABORTED => ['Pago anulado', 'Anulaste el pago en Webpay. No se realizó ningún cobro.'],
        Payment::TIMEOUT, Payment::ABANDONED => ['Se agotó el tiempo', 'El pago no se completó.'],
        Payment::ERROR => ['No se completó el pago', 'Hubo un problema en el formulario de pago. No confirmamos tu hora.'],
        Payment::REFUNDED => ['Pago devuelto', 'La hora dejó de estar disponible y solicitamos la devolución del pago.'],
        Payment::SLOT_LOST => ['La hora ya no está disponible', 'No confirmamos el cobro.'],
        default => ['Estamos verificando tu pago', 'No vuelvas a pagar por ahora. Si ves un cargo y no recibes confirmación, contáctanos con el número de orden.'],
    };

    $canRetry = in_array($payment->status, [Payment::REJECTED, Payment::ABORTED, Payment::TIMEOUT, Payment::ERROR], true)
        && $booking->isHeld();
@endphp

@section('title', $title)

@section('content')
<div class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white p-6">
    @include('partials.test-mode')

    <h1 class="text-xl font-semibold {{ $payment->status === Payment::APPROVED ? 'text-teal-700' : '' }}">{{ $title }}</h1>
    <p class="mt-2 text-sm text-slate-600">{{ $message }}</p>

    <dl class="mt-5 space-y-3 text-sm">
        <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Profesional</dt>
            <dd class="text-right font-medium">{{ $booking->professional->name }}</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Fecha y hora</dt>
            <dd class="text-right font-medium">{{ $dateLabel }} h (hora de Chile)</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Sesión</dt>
            <dd class="text-right font-medium">{{ $booking->service->label() }} · {{ $booking->service->duration_minutes }} min</dd>
        </div>
        <div class="flex justify-between gap-4 border-t border-slate-200 pt-3">
            <dt class="text-slate-500">Monto</dt>
            <dd class="text-right font-semibold">${{ number_format($payment->amount, 0, ',', '.') }}</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Orden de compra</dt>
            <dd class="break-all text-right font-medium">{{ $payment->buy_order }}</dd>
        </div>
        @if ($payment->status === Payment::APPROVED)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Código de autorización</dt>
                <dd class="text-right font-medium">{{ $payment->authorization_code }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Tarjeta</dt>
                <dd class="text-right font-medium">**** {{ $payment->card_last4 }}</dd>
            </div>
            @if ($payment->installments > 1)
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Cuotas</dt>
                    <dd class="text-right font-medium">{{ $payment->installments }}</dd>
                </div>
            @endif
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Fecha del pago</dt>
                <dd class="text-right font-medium">{{ $paidAt }}</dd>
            </div>
        @endif
    </dl>

    @if ($canRetry)
        <a href="{{ route('hold.show', $booking->public_id) }}"
           class="mt-6 block rounded-lg bg-teal-600 px-4 py-3 text-center font-medium text-white hover:bg-teal-700">
            Volver a intentar
        </a>
    @elseif ($payment->status === Payment::APPROVED)
        <a href="https://psicologosahora.cl/" class="mt-6 block text-center text-sm text-teal-700 underline">
            Volver a psicologosahora.cl
        </a>
    @elseif (! in_array($payment->status, Payment::IN_PROGRESS, true) && $payment->status !== Payment::REVIEW)
        <a href="{{ route('booking.show', $booking->professional->slug) }}"
           class="mt-6 block text-center text-sm text-teal-700 underline">
            Volver al calendario
        </a>
    @endif
</div>
@endsection
