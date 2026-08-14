<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Ingresar — Trazabilidad de neumáticos</title>
    <script>
        document.documentElement.dataset.type = localStorage.getItem('tn-scale') || 'md';
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-hero" aria-hidden="true">
            <div class="auth-hero__mark">TN</div>
            <p class="auth-hero__k">Flota</p>
            <h1>Trazabilidad de cubiertas</h1>
            <p>Historial por cubierta, no por patente. Stock, rotación y planilla en un mismo lugar.</p>
        </section>

        <section class="auth-panel" aria-labelledby="login-title">
            <form method="POST" action="{{ route('login') }}" class="auth-form">
                @csrf
                <div>
                    <p class="auth-kicker">Bienvenido</p>
                    <h2 id="login-title">Ingresar</h2>
                    <p class="auth-lead">Escribí tu usuario y contraseña. Las letras son grandes para leer con comodidad.</p>
                </div>

                <div class="field">
                    <label for="username">Usuario</label>
                    <input id="username" class="inp" name="username" value="{{ old('username', 'admin') }}" autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="password">Contraseña</label>
                    <input id="password" class="inp" type="password" name="password" value="password" autocomplete="current-password" required>
                </div>

                @error('username')
                    <p class="form-error" role="alert">{{ $message }}</p>
                @enderror

                <button class="btn btn-primary w-full" type="submit">Ingresar</button>
                <p class="auth-hint">Demo: <strong>admin</strong> / <strong>password</strong></p>
            </form>
        </section>
    </main>
</body>
</html>
