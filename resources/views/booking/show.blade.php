@extends('layouts.app')

@section('title', 'Reservar con '.$professional->name)

@section('content')
<div x-data='bookingCalendar(@json($config))' class="space-y-6">

    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="text-sm text-slate-500" x-text="professional.title || 'Psicólogo/a'"></p>
        <h1 class="text-2xl font-semibold" x-text="professional.name"></h1>

        <template x-if="services.length > 1">
            <div class="mt-4">
                <label for="service" class="mb-1 block text-sm font-medium">Tipo de sesión</label>
                <select id="service" x-model="serviceId" @change="changeService()"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 sm:w-auto">
                    <template x-for="s in services" :key="s.id">
                        <option :value="s.id" x-text="`${s.label} · ${s.price}`"></option>
                    </template>
                </select>
            </div>
        </template>

        <p class="mt-3 text-sm text-slate-600">
            <span x-text="service.label"></span> ·
            <span x-text="`${service.minutes} min`"></span> ·
            <span class="font-medium" x-text="service.price"></span>
        </p>
    </section>

    <div x-show="error" x-cloak role="alert"
         class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></div>

    <div class="grid gap-6 md:grid-cols-2">

        {{-- Calendario --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5" aria-label="Calendario">
            <div class="mb-4 flex items-center justify-between">
                <button type="button" @click="changeMonth(-1)" :disabled="!canGoBack"
                        class="rounded-lg px-3 py-1 text-lg hover:bg-slate-100 disabled:opacity-30" aria-label="Mes anterior">‹</button>
                <h2 class="font-medium" x-text="monthLabel"></h2>
                <button type="button" @click="changeMonth(1)" :disabled="!canGoForward"
                        class="rounded-lg px-3 py-1 text-lg hover:bg-slate-100 disabled:opacity-30" aria-label="Mes siguiente">›</button>
            </div>

            <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-slate-500">
                <template x-for="d in ['L','M','M','J','V','S','D']"><div x-text="d"></div></template>
            </div>

            <div class="mt-1 grid grid-cols-7 gap-1" :class="loadingDays ? 'opacity-50' : ''">
                <template x-for="(cell, i) in cells" :key="i">
                    <div class="aspect-square">
                        <template x-if="cell">
                            <button type="button" @click="pickDay(cell)" :disabled="!cell.available"
                                    :aria-pressed="selectedDate === cell.iso"
                                    class="flex h-full w-full items-center justify-center rounded-lg text-sm"
                                    :class="selectedDate === cell.iso
                                        ? 'bg-teal-600 font-semibold text-white'
                                        : (cell.available ? 'bg-teal-50 font-medium text-teal-800 hover:bg-teal-100' : 'cursor-not-allowed text-slate-300')"
                                    x-text="cell.day"></button>
                        </template>
                    </div>
                </template>
            </div>

            <p class="mt-4 text-xs text-slate-500">
                Los días en verde tienen horas libres. Horario de Chile (<span x-text="timezone"></span>).
            </p>
        </section>

        {{-- Horas --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5" aria-label="Horas disponibles">
            <h2 class="mb-3 font-medium">Horas disponibles</h2>

            <p x-show="!selectedDate" class="text-sm text-slate-500">Elige un día del calendario para ver las horas.</p>
            <p x-show="selectedDate" x-cloak class="mb-3 text-sm text-slate-600" x-text="selectedDateLabel"></p>
            <p x-show="loadingSlots" x-cloak class="text-sm text-slate-500">Cargando horas…</p>
            <p x-show="selectedDate && !loadingSlots && slots.length === 0" x-cloak class="text-sm text-slate-500">
                No quedan horas libres este día.
            </p>

            <div class="grid grid-cols-3 gap-2">
                <template x-for="slot in slots" :key="slot.start">
                    <button type="button" @click="pickSlot(slot)" :aria-pressed="selectedSlot && selectedSlot.start === slot.start"
                            class="rounded-lg border px-2 py-2 text-sm"
                            :class="selectedSlot && selectedSlot.start === slot.start
                                ? 'border-teal-600 bg-teal-600 font-semibold text-white'
                                : 'border-slate-300 hover:border-teal-500 hover:bg-teal-50'"
                            x-text="slot.time"></button>
                </template>
            </div>

            <div x-show="selectedSlot" x-cloak class="mt-6 rounded-lg bg-slate-50 p-4 text-sm">
                <p class="font-medium">Tu hora</p>
                <p class="mt-1"><span x-text="selectedDateLabel"></span>, <span x-text="selectedSlot && selectedSlot.time"></span> h</p>
                <p class="text-slate-600"><span x-text="service.label"></span> · <span x-text="service.price"></span></p>
                <button type="button" @click="openForm()" x-show="!showForm"
                        class="mt-4 w-full rounded-lg bg-teal-600 px-4 py-2 font-medium text-white hover:bg-teal-700">
                    Continuar
                </button>
            </div>
        </section>
    </div>

    @include('booking.partials.form')
</div>
@endsection

@push('head')
    {{-- Cloudflare pide cargarlo desde esta URL exacta, sin copiarlo ni cachearlo. --}}
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
@endpush
