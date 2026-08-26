<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inicio') — {{ config('app.name', 'Rodante') }}</title>
    <meta name="theme-color" content="#c8102e">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/rodante-icon-180.png') }}">
    <script>
        document.documentElement.dataset.type = localStorage.getItem('rodante-scale') || localStorage.getItem('rodanta-scale') || localStorage.getItem('tn-scale') || 'md';
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<div class="app-shell" id="appShell">
    <button type="button" class="app-backdrop" id="navBackdrop" tabindex="-1" aria-hidden="true"></button>

    <aside class="app-sidebar" id="sidebar" aria-label="Navegación principal">
        <div class="sb-top">
            <a href="{{ route('dashboard') }}" class="sb-brand" aria-label="{{ config('app.name', 'Rodante') }}">
                <x-brand-logo />
            </a>
            <button type="button" class="btn btn-ghost btn-ico sb-close lg:hidden" id="navClose" aria-label="Cerrar menú">
                <x-icon name="x" class="w-6 h-6" />
            </button>
        </div>

        <nav class="sb-nav" aria-label="Secciones">
            <div class="sb-group">
                <div class="sb-lbl">Operación</div>
                <x-nav-link :href="route('dashboard')" icon="home" label="Tablero" match="dashboard" />
                <x-nav-link :href="route('field.index')" icon="search" label="Campo" match="field.*" />
                <x-nav-link :href="route('units.index')" icon="truck" label="Unidades" match="units.*" />
                <x-nav-link :href="route('tires.stock')" icon="boxes" label="Stock" match="tires.stock" />
                <x-nav-link :href="route('tires.index')" icon="circle" label="Neumáticos" :match="['tires.index', 'tires.show']" />
                <x-nav-link :href="route('purchases.index')" icon="cart" label="Compras" match="purchases.*" />
                <x-nav-link :href="route('work-orders.index')" icon="grid" label="Órdenes" match="work-orders.*" />
                <x-nav-link :href="route('odometers.index')" icon="gauge" label="Odómetros" match="odometers.*" />
            </div>
            <div class="sb-group">
                <div class="sb-lbl">Consulta</div>
                <x-nav-link :href="route('reports.kilometers')" icon="chart" label="Km por cubierta" match="reports.kilometers" />
                <x-nav-link :href="route('reports.cost-km')" icon="chart" label="Costo / km" match="reports.cost-km" />
                <x-nav-link :href="route('reports.cost-attribution')" icon="chart" label="Costo unidad/posición" match="reports.cost-attribution" />
                <x-nav-link :href="route('reports.inventory')" icon="boxes" label="Inventario teórico" match="reports.inventory" />
                <x-nav-link :href="route('inventories.index')" icon="boxes" label="Inventario físico" match="inventories.*" />
                <x-nav-link :href="route('reports.consumption')" icon="grid" label="Consumo" match="reports.consumption" />
                <x-nav-link :href="route('reports.incidents')" icon="alert" label="Incidencias" match="reports.incidents" />
                <x-nav-link :href="route('reports.predictive')" icon="chart" label="Predictivo" match="reports.predictive" />
                <x-nav-link :href="route('reports.audit')" icon="shield" label="Movimientos" match="reports.audit" />
                @if(auth()->user()->role->canViewTelemetry())
                    <x-nav-link :href="route('reports.telemetry')" icon="gauge" label="Telemetría" match="reports.telemetry" />
                @endif
                @if(auth()->user()->role->canRetireOrRecap())
                    <x-nav-link :href="route('integrity.index')" icon="shield" label="Integridad" match="integrity.*" />
                @endif
                <x-nav-link :href="route('costs.index')" icon="chart" label="Costos" match="costs.*" />
                <x-nav-link :href="route('notifications.index')" icon="alert" label="Avisos" match="notifications.*" />
                <x-nav-link :href="route('help.index')" icon="book" label="Ayuda" match="help.*" />
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
                    <x-nav-link :href="route('shops.index')" icon="pin" label="Recapadoras" match="shops.*" />
                    <x-nav-link :href="route('users.index')" icon="users" label="Usuarios" match="users.*" />
                </div>
            @endif
        </nav>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button type="button" class="btn btn-ghost btn-ico lg:hidden" id="navOpen" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false">
                <x-icon name="menu" class="w-6 h-6" />
            </button>

            <form class="top-search" method="GET" action="{{ route('search') }}" role="search" data-suggest="{{ route('search.suggest') }}">
                <label class="sr-only" for="topSearch">Buscar patente o número de cubierta</label>
                <x-icon name="search" class="top-search__ico" />
                <input id="topSearch" name="q" value="{{ request('q') }}" type="search" placeholder="Patente o Nº de cubierta…" autocomplete="off" aria-autocomplete="list" aria-controls="searchSuggest" aria-expanded="false">
                <div id="searchSuggest" class="search-suggest" hidden role="listbox" aria-label="Sugerencias"></div>
            </form>

            <div class="top-right">
                <div class="type-ctl" role="group" aria-label="Tamaño de letra">
                    <span>Letra</span>
                    <button type="button" data-type="md" aria-label="Letra normal">A</button>
                    <button type="button" data-type="lg" aria-label="Letra grande">A+</button>
                    <button type="button" data-type="xl" aria-label="Letra extra grande">A++</button>
                </div>
                <time class="top-date" datetime="{{ now()->toDateString() }}">{{ now()->format('d/m/Y') }}</time>
                <span class="top-chip">{{ auth()->user()->role->label() }}</span>
                <div class="top-user">
                    <span class="sb-av" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                    <span class="top-user__meta">
                        <span class="sb-user-n">{{ auth()->user()->name }}</span>
                        <span class="sb-user-r">{{ auth()->user()->username }}</span>
                    </span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-ghost" type="submit" aria-label="Cerrar sesión">
                        <x-icon name="logout" class="w-5 h-5" /> Salir
                    </button>
                </form>
            </div>
        </header>

        <main class="app-page @yield('page_class')" id="contenido" tabindex="-1">
            @hasSection('kicker')
                <p class="sr-only">@yield('kicker')</p>
            @endif
            <div class="toast-host" aria-live="polite" aria-relevant="additions"></div>
            @if(session('success'))
                <div class="flash flash--ok toast" role="status">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="flash flash--err toast" role="alert">{{ $errors->first() }}</div>
                <script>window.__rodanteFieldErrors = @json(array_keys($errors->getMessages()));</script>
            @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
