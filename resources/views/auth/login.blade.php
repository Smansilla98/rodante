<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Ingresar — {{ config('app.name', 'Rodante') }}</title>
    <meta name="theme-color" content="#c8102e">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/rodante-icon-180.png') }}">
    <script>
        document.documentElement.dataset.type = localStorage.getItem('rodante-scale') || localStorage.getItem('rodanta-scale') || localStorage.getItem('tn-scale') || 'md';
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-hero" aria-hidden="true">
            <x-brand-logo variant="auth" />
            <p class="auth-hero__k">Rodante</p>
            <h1>Gestión inteligente de neumáticos</h1>
            <p>Historial por cubierta, no por patente. Stock, rotación y planilla en un mismo lugar.</p>
        </section>

        <section class="auth-panel" aria-labelledby="login-title">
            <form method="POST" action="{{ route('login') }}" class="auth-form">
                @csrf
                <div class="auth-panel__brand">
                    <img src="{{ asset('brand/rodante-app-icon.png') }}" alt="{{ config('app.name', 'Rodante') }}" class="auth-panel__icon">
                    <p class="auth-kicker">Bienvenido a Rodante</p>
                    <h2 id="login-title">Ingresar</h2>
                    <p class="auth-lead">Escribí tu usuario y contraseña. Las letras son grandes para leer con comodidad.</p>
                </div>

                <div class="field">
                    <label for="username">Usuario</label>
                    <input id="username" class="inp" name="username" value="{{ old('username', app()->environment('local') ? 'admin' : '') }}" autocomplete="username" required @error('username') aria-invalid="true" aria-describedby="err-username" @enderror>
                    <x-field-error name="username" />
                </div>
                <div class="field">
                    <label for="password">Contraseña</label>
                    <input id="password" class="inp" type="password" name="password" value="{{ app()->environment('local') ? 'password' : '' }}" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="err-password" @enderror>
                    <x-field-error name="password" />
                </div>

                <button class="btn btn-primary w-full" type="submit">Ingresar</button>
                <p class="auth-hint"><a href="{{ route('password.request') }}">Olvidé mi contraseña</a></p>
                @if(session('status'))
                    <div class="flash flash--ok" role="status">{{ session('status') }}</div>
                @endif
                @if(app()->environment('local'))
                    <p class="auth-hint">Demo: <strong>admin</strong> / <strong>password</strong></p>
                @endif
            </form>
        </section>
    </main>
</body>
</html>
