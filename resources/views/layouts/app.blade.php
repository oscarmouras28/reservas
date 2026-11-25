<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Reservar hora') · Psicólogos Ahora</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4">
            <a href="https://psicologosahora.cl/" class="text-lg font-semibold text-teal-700">Psicólogos Ahora</a>
            <span class="text-sm text-slate-500">Reserva online</span>
        </div>
    </header>

    <main class="mx-auto max-w-4xl px-4 py-8">
        @yield('content')
    </main>

    <footer class="mx-auto max-w-4xl px-4 pb-10 text-center text-xs text-slate-500">
        Psicólogos Ahora · Atención psicológica online
    </footer>
</body>
</html>
