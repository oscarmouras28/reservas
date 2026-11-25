@extends('layouts.app')

@section('title', $secondsLeft > 0 ? 'Tu hora está apartada' : 'La reserva venció')

@section('content')
<div class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white p-6">
    @if ($secondsLeft > 0)
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

        <button type="button" disabled
                class="mt-6 w-full cursor-not-allowed rounded-lg bg-slate-300 px-4 py-3 font-medium text-white">
            Pagar
        </button>
        <p class="mt-2 text-center text-xs text-slate-500">El pago se habilita en la siguiente etapa.</p>

        <form method="POST" action="{{ route('hold.release', $booking->public_id) }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-sm text-teal-700 underline">Cambiar de hora</button>
        </form>
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
