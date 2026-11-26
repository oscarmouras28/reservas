@extends('layouts.app')

@section('title', 'Redirigiendo a Webpay')

@section('content')
<div class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white p-6 text-center">
    <h1 class="text-xl font-semibold">Te llevamos a Webpay</h1>
    <p class="mt-2 text-sm text-slate-600">Si no avanza solo, presiona el botón.</p>

    <form id="webpay" method="POST" action="{{ $redirect->url }}" class="mt-5">
        <input type="hidden" name="token_ws" value="{{ $redirect->token }}">
        <button type="submit" class="w-full rounded-lg bg-teal-600 px-4 py-3 font-medium text-white hover:bg-teal-700">
            Ir a pagar
        </button>
    </form>
</div>
<script>document.getElementById('webpay').submit();</script>
@endsection
