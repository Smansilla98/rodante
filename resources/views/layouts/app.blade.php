<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inicio') — Trazabilidad de neumáticos</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="app-shell" id="appShell">
    <button type="button" class="app-backdrop" id="navBackdrop" aria-label="Cerrar menú"></button>

    <aside class="app-sidebar" id="sidebar">
        <a href="{{ route('dashboard') }}" class="sb-brand">
            <span class="sb-mark">TN</span>
            <span>
                <span class="sb-brand-k">Flota</span>
                <span class="sb-brand-t">Trazabilidad</span>
            </span>
        </a>

        <nav class="sb-nav" aria-label="Principal">
            <div class="sb-group">
                <div class="sb-lbl">Operación</div>
                <x-nav-link :href="route('dashboard')" icon="home" label="Tablero" match="dashboard" />
                <x-nav-link :href="route('units.index')" icon="truck" label="Unidades" match="units.*" />
                <x-nav-link :href="route('tires.stock')" icon="boxes" label="Stock" match="tires.stock" />
                <x-nav-link :href="route('tires.index')" icon="circle" label="Neumáticos" :match="['tires.index', 'tires.show']" />
                <x-nav-link :href="route('purchases.index')" icon="cart" label="Compras" match="purchases.*" />
                <x-nav-link :href="route('odometers.index')" icon="gauge" label="Odómetros" match="odometers.*" />
            </div>
            <div class="sb-group">
                <div class="sb-lbl">Consulta</div>
                <x-nav-link :href="route('reports.kilometers')" icon="chart" label="Km por cubierta" match="reports.kilometers" />
                <x-nav-link :href="route('reports.consumption')" icon="grid" label="Consumo" match="reports.consumption" />
                <x-nav-link :href="route('reports.incidents')" icon="alert" label="Incidencias" match="reports.incidents" />
                <x-nav-link :href="route('reports.audit')" icon="shield" label="Auditoría" match="reports.audit" />
            </div>
            @if(auth()->user()->role->canManageCatalogs())
                <div class="sb-group">
                    <div class="sb-lbl">Catálogo</div>
                    <x-nav-link :href="route('brands.index')" icon="tag" label="Marcas" match="brands.*" />
                    <x-nav-link :href="route('models.index')" icon="circle" label="Modelos" match="models.*" />
                    <x-nav-link :href="route('sizes.index')" icon="ruler" label="Medidas" match="sizes.*" />
                    <x-nav-link :href="route('fleets.index')" icon="fleet" label="Flotas" match="fleets.*" />
                    <x-nav-link :href="route('bases.index')" icon="pin" label="Bases" match="bases.*" />
                    <x-nav-link :href="route('suppliers.index')" icon="cart" label="Proveedores" match="suppliers.*" />
                    <x-nav-link :href="route('types.index')" icon="grid" label="Tipos y motivos" match="types.*" />
                    <x-nav-link :href="route('users.index')" icon="users" label="Usuarios" match="users.*" />
                </div>
            @endif
        </nav>

        <div class="sb-foot">
            <div class="sb-user">
                <span class="sb-av">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <span class="min-w-0">
                    <span class="sb-user-n">{{ auth()->user()->name }}</span>
                    <span class="sb-user-r">{{ auth()->user()->role->label() }}</span>
                </span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="sb-out" type="submit" title="Salir">
                    <x-icon name="logout" class="w-4 h-4" />
                    Salir
                </button>
            </form>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button type="button" class="btn btn-ghost btn-ico md:hidden" id="navOpen" aria-label="Abrir menú">
                <x-icon name="menu" class="w-5 h-5" />
            </button>
            <div class="min-w-0">
                <div class="top-kicker">@yield('kicker', 'Flota')</div>
                <div class="top-title">@yield('title', 'Inicio')</div>
            </div>
            <div class="top-right">
                <span class="top-date">{{ now()->format('d/m/Y') }}</span>
                <span class="top-chip">{{ auth()->user()->role->label() }}</span>
            </div>
        </header>

        <div class="app-page">
            @if(session('success'))
                <div class="flash flash--ok">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="flash flash--err">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </div>
    </div>
</div>
</body>
</html>
