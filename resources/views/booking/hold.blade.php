@extends('layouts.app')

@php
    $confirmed = $booking->status === \App\Models\Booking::CONFIRMED;
    $title = $confirmed ? 'Tu hora está reservada' : ($secondsLeft > 0 ? 'Tu hora está apartada' : 'La reserva venció');
@endphp

@section('title', $title)

@section('content')
<div class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white p-6">
    @include('partials.test-mode')

    @if (session('payment_error'))
        <p role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('payment_error') }}
        </p>
    @endif

    @if ($confirmed)
        <h1 class="text-xl font-semibold text-teal-700">Tu hora está reservada</h1>
        <p class="mt-2 text-sm text-slate-600">
            {{ $booking->professional->name }} · {{ $dateLabel }} h (hora de Chile). El pago ya está registrado.
        </p>
    @elseif ($secondsLeft > 0)
        <div x-data="holdTimer({{ $secondsLeft }})">
            <h1 class="text-xl font-semibold">Tu hora está apartada</h1>
            <p class="mt-1 text-sm text-slate-600">
                Te quedan <span class="font-semibold tabular-nums text-teal-700" x-text="clock"></span> minutos.
            </p>
        </div>

        <dl class="mt-5 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Profesional</dt>
                <dd class="text-right font-medium">{{ $booking->professional->name }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Fecha y hora</dt>
                <dd class="text-right font-medium">{{ $dateLabel }} h</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Sesión</dt>
                <dd class="text-right font-medium">{{ $booking->service->label() }} · {{ $booking->service->duration_minutes }} min</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">A nombre de</dt>
                <dd class="text-right font-medium">{{ $booking->patient_name }}</dd>
            </div>
            <div class="flex justify-between gap-4 border-t border-slate-200 pt-3 text-base">
                <dt class="font-medium">Total</dt>
                <dd class="font-semibold">${{ number_format($booking->price, 0, ',', '.') }}</dd>
            </div>
        </dl>

        @if ($paymentInProgress)
            <p class="mt-5 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
                Iniciaste un pago. Si no lo completaste, puedes intentarlo de nuevo.
                Si ya pagaste, no vuelvas a pagar: estamos esperando la confirmación.
            </p>
        @endif

        <form method="POST" action="{{ route('payment.start', $booking->public_id) }}" class="mt-6"
              x-data="{ sending: false }" @submit="sending = true">
            @csrf
            <button type="submit" :disabled="sending"
                    class="w-full rounded-lg bg-teal-600 px-4 py-3 font-medium text-white hover:bg-teal-700 disabled:opacity-60">
                Pagar con Webpay
            </button>
        </form>

        @unless ($paymentInProgress)
            <form method="POST" action="{{ route('hold.release', $booking->public_id) }}" class="mt-4 text-center">
                @csrf
                <button type="submit" class="text-sm text-teal-700 underline">Cambiar de hora</button>
            </form>
        @endunless
    @elseif ($paymentInProgress)
        <h1 class="text-xl font-semibold">Estamos verificando tu pago</h1>
        <p class="mt-2 text-sm text-slate-600">
            No vuelvas a pagar por ahora. Actualiza esta página en unos minutos.
        </p>
    @else
        <h1 class="text-xl font-semibold">La reserva venció</h1>
        <p class="mt-2 text-sm text-slate-600">
            La hora dejó de estar apartada. Puedes elegirla de nuevo si sigue libre.
        </p>
        <a href="{{ route('booking.show', $booking->professional->slug) }}"
           class="mt-5 block rounded-lg bg-teal-600 px-4 py-3 text-center font-medium text-white hover:bg-teal-700">
            Volver al calendario
        </a>
    @endif
</div>
@endsection
