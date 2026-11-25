{{-- Datos del paciente. Vive dentro del componente Alpine bookingCalendar. --}}
@php
    $input = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600';
@endphp
<section x-show="showForm && selectedSlot" x-cloak x-ref="form"
         class="rounded-xl border border-slate-200 bg-white p-5" aria-label="Tus datos">
    <h2 class="text-lg font-medium">Tus datos</h2>
    <p class="mt-1 text-sm text-slate-600">
        Reservarás el <span x-text="selectedDateLabel.toLowerCase()"></span> a las
        <span x-text="selectedSlot && selectedSlot.time"></span> h ·
        <span x-text="service.price"></span>
    </p>

    <form @submit.prevent="submit()" class="mt-4 grid gap-4 sm:grid-cols-2" novalidate>
        <div class="sm:col-span-2">
            <label for="name" class="mb-1 block text-sm font-medium">Nombre completo</label>
            <input id="name" type="text" x-model="form.name" autocomplete="name" maxlength="120" class="{{ $input }}">
            <p x-show="errors.name" class="mt-1 text-sm text-red-700" x-text="errors.name && errors.name[0]"></p>
        </div>

        <div>
            <label for="document_number" class="mb-1 block text-sm font-medium">RUT o pasaporte</label>
            <div class="flex gap-2">
                <select x-model="form.document_type" aria-label="Tipo de documento"
                        class="rounded-lg border border-slate-300 bg-white px-2 py-2">
                    <option value="rut">RUT</option>
                    <option value="passport">Pasaporte</option>
                </select>
                <input id="document_number" type="text" x-model="form.document_number" maxlength="20" class="{{ $input }}"
                       :placeholder="form.document_type === 'rut' ? '12.345.678-5' : 'N.º de pasaporte'">
            </div>
            <p x-show="errors.document_number" class="mt-1 text-sm text-red-700" x-text="errors.document_number && errors.document_number[0]"></p>
        </div>

        <div>
            <label for="phone" class="mb-1 block text-sm font-medium">Teléfono</label>
            <input id="phone" type="tel" x-model="form.phone" autocomplete="tel" maxlength="25" class="{{ $input }}" placeholder="+56 9 1234 5678">
            <p x-show="errors.phone" class="mt-1 text-sm text-red-700" x-text="errors.phone && errors.phone[0]"></p>
        </div>

        <div class="sm:col-span-2">
            <label for="email" class="mb-1 block text-sm font-medium">Correo</label>
            <input id="email" type="email" x-model="form.email" autocomplete="email" maxlength="190" class="{{ $input }}">
            <p x-show="errors.email" class="mt-1 text-sm text-red-700" x-text="errors.email && errors.email[0]"></p>
        </div>

        <div class="sm:col-span-2">
            <label for="address" class="mb-1 block text-sm font-medium">Dirección</label>
            <input id="address" type="text" x-model="form.address" autocomplete="street-address" maxlength="200" class="{{ $input }}">
            <p x-show="errors.address" class="mt-1 text-sm text-red-700" x-text="errors.address && errors.address[0]"></p>
        </div>

        <div class="space-y-2 text-sm sm:col-span-2">
            <label class="flex items-start gap-2">
                <input type="checkbox" x-model="form.privacy" class="mt-1 h-4 w-4 accent-teal-600">
                <span>Acepto la
                    <a href="{{ config('reservas.privacy_url') }}" target="_blank" rel="noopener"
                       class="font-medium text-teal-700 underline">política de privacidad</a>.</span>
            </label>
            <p x-show="errors.privacy" class="text-red-700" x-text="errors.privacy && errors.privacy[0]"></p>

            <label class="flex items-start gap-2">
                <input type="checkbox" x-model="form.adult" class="mt-1 h-4 w-4 accent-teal-600">
                <span>Declaro ser mayor de 18 años.</span>
            </label>
            <p x-show="errors.adult" class="text-red-700" x-text="errors.adult && errors.adult[0]"></p>
        </div>

        <div class="sm:col-span-2">
            <div x-ref="turnstile"></div>
            <p x-show="errors.turnstile" class="mt-1 text-sm text-red-700" x-text="errors.turnstile && errors.turnstile[0]"></p>
        </div>

        <div class="sm:col-span-2">
            <p x-show="formError" role="alert" class="mb-2 text-sm text-red-700" x-text="formError"></p>
            <button type="submit" :disabled="submitting"
                    class="w-full rounded-lg bg-teal-600 px-4 py-3 font-medium text-white hover:bg-teal-700 disabled:cursor-wait disabled:opacity-60">
                <span x-text="submitting ? 'Guardando…' : 'Reservar mi hora'"></span>
            </button>
            <p class="mt-2 text-center text-xs text-slate-500">
                Apartaremos tu hora durante {{ config('reservas.hold_minutes') }} minutos.
            </p>
        </div>
    </form>
</section>
