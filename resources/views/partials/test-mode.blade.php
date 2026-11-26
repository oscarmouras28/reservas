@if (config('webpay.environment') !== 'production')
    <p class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-center text-xs font-medium text-amber-900">
        Modo de prueba: no se cobra dinero real.
    </p>
@endif
