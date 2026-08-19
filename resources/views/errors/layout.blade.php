<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }} — {{ config('app.name', 'Rodante') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="err-body">
    <main class="err-shell">
        <img src="{{ asset('brand/rodante-app-icon.png') }}" alt="" class="err-mark" width="56" height="56">
        <p class="err-kicker">Rodante</p>
        <p class="err-code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="err-actions">
            <a class="btn btn-primary" href="{{ url('/') }}">Ir al inicio</a>
            <a class="btn btn-ghost" href="javascript:history.back()">Volver</a>
        </div>
    </main>
</body>
</html>
