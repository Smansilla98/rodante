<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Recuperar contraseña — {{ config('app.name', 'Rodante') }}</title>
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
            <h1>Recuperar acceso</h1>
            <p>Te enviamos un enlace al email de la cuenta. No revelamos si el usuario existe.</p>
        </section>
        <section class="auth-panel" aria-labelledby="forgot-title">
            <form method="POST" action="{{ route('password.email') }}" class="auth-form">
                @csrf
                <div class="auth-panel__brand">
                    <p class="auth-kicker">Seguridad</p>
                    <h2 id="forgot-title">Olvidé mi contraseña</h2>
                    <p class="auth-lead">Ingresá tu usuario o email. Si la cuenta está activa y tiene email, vas a recibir instrucciones.</p>
                </div>
                @if(session('status'))
                    <div class="flash flash--ok" role="status">{{ session('status') }}</div>
                @endif
                <div class="field">
                    <label for="login">Usuario o email</label>
                    <input id="login" class="inp" name="login" value="{{ old('login') }}" autocomplete="username" required>
                    <x-field-error name="login" />
                </div>
                <button class="btn btn-primary w-full" type="submit">Enviar enlace</button>
                <p class="auth-hint"><a href="{{ route('login') }}">Volver al ingreso</a></p>
            </form>
        </section>
    </main>
</body>
</html>
