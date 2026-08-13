<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar — Trazabilidad de neumáticos</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <form method="POST" action="{{ route('login') }}" class="auth-card space-y-5">
        @csrf
        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-amber-500">Flota</div>
            <h1 class="text-2xl font-semibold mt-1">Trazabilidad de neumáticos</h1>
            <p class="text-sm text-slate-400 mt-1">Historial por cubierta, no por patente.</p>
        </div>
        <label class="field">
            <span class="text-slate-400">Usuario</span>
            <input class="inp" name="username" value="{{ old('username', 'admin') }}" autocomplete="username" required>
        </label>
        <label class="field">
            <span class="text-slate-400">Contraseña</span>
            <input class="inp" type="password" name="password" value="password" autocomplete="current-password" required>
        </label>
        @error('username') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        <button class="btn btn-primary w-full">Ingresar</button>
        <p class="text-xs text-slate-500">Demo: admin / password</p>
    </form>
</body>
</html>
