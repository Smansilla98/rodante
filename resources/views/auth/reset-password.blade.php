<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Nueva contraseña — {{ config('app.name', 'Rodante') }}</title>
    <meta name="theme-color" content="#c8102e">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-hero" aria-hidden="true">
            <x-brand-logo variant="auth" />
            <p class="auth-hero__k">Rodante</p>
            <h1>Elegí una contraseña nueva</h1>
            <p>Mínimo 10 caracteres, con letras y números.</p>
        </section>
        <section class="auth-panel" aria-labelledby="reset-title">
            <form method="POST" action="{{ route('password.update') }}" class="auth-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="auth-panel__brand">
                    <p class="auth-kicker">Seguridad</p>
                    <h2 id="reset-title">Nueva contraseña</h2>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" class="inp" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
                    <x-field-error name="email" />
                </div>
                <div class="field">
                    <label for="password">Contraseña</label>
                    <input id="password" class="inp" type="password" name="password" required autocomplete="new-password">
                    <x-field-error name="password" />
                </div>
                <div class="field">
                    <label for="password_confirmation">Repetir contraseña</label>
                    <input id="password_confirmation" class="inp" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>
                <button class="btn btn-primary w-full" type="submit">Guardar contraseña</button>
                <p class="auth-hint"><a href="{{ route('login') }}">Volver al ingreso</a></p>
            </form>
        </section>
    </main>
</body>
</html>
