<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ config('app.name', 'Rodante') }} — Gestión inteligente de neumáticos para flotas</title>
    <meta name="description" content="Rodante sigue cada cubierta de la flota con número propio: historial de km, rotaciones, pinchaduras, recapados y bajas. El dato vive en la cubierta, no en la patente.">
    <meta name="theme-color" content="#c8102e">
    <meta name="robots" content="noindex, nofollow">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/rodante-icon-180.png') }}">
    <script src="{{ asset('js/scale-boot.js') }}"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="landing-body">
    <header class="landing-nav">
        <a href="{{ route('home') }}" class="landing-nav__brand">
            <img src="{{ asset('brand/rodante-app-icon.png') }}" alt="" width="36" height="36" decoding="async">
            <span>{{ config('app.name', 'Rodante') }}</span>
        </a>
        <nav class="landing-nav__links" aria-label="Secciones">
            <a href="#que-es">Qué es</a>
            <a href="#funciones">Funciones</a>
            <a href="#roles">Roles</a>
            <a href="#reglas">Reglas</a>
        </nav>
        <a href="{{ route('login') }}" class="btn btn-primary landing-nav__cta">Ingresar</a>
    </header>

    <main>
        <section class="landing-hero">
            <div class="landing-hero__text">
                <p class="landing-kicker">Trazabilidad de neumáticos</p>
                <h1>Gestión inteligente de neumáticos para flotas</h1>
                <p class="landing-lead">
                    Rodante sigue <strong>cada cubierta</strong> de tractores, semirremolques, tanques y bateas
                    con número propio: kilómetros, rotaciones, pinchaduras, recapados y bajas, en una sola historia.
                </p>
                <div class="landing-hero__actions">
                    <a href="{{ route('login') }}" class="btn btn-primary">Ingresar al sistema</a>
                    <a href="#que-es" class="btn landing-btn-ghost">Conocer el proyecto</a>
                </div>
            </div>
            <div class="landing-hero__art">
                <img src="{{ asset('brand/rodante-logo.png') }}" alt="Logo de Rodante" width="420" height="420" decoding="async">
            </div>
        </section>

        <section id="que-es" class="landing-section">
            <p class="landing-kicker">Qué es Rodante</p>
            <h2>El dato vive en la cubierta, no en la patente</h2>
            <div class="landing-split">
                <p>
                    En una flota, las cubiertas se montan, se rotan, se reparan, se recapan y pasan de una unidad a otra.
                    Si el historial se anota por patente, se pierde en cada cambio. Rodante identifica cada neumático
                    (por ejemplo <code>FH:01 Nº30363</code>) y registra todo lo que le pasa durante su vida útil.
                </p>
                <p>
                    La planilla visual del chasis muestra qué cubierta ocupa cada ubicación. El acoplado usa el odómetro
                    del tractor mientras está unido, así los kilómetros se asignan solos a cada cubierta montada.
                    Con eso se responde lo importante: cuánto rindió cada neumático, cuánto costó por km y cuándo conviene darlo de baja.
                </p>
            </div>
            <ul class="landing-stats">
                <li><strong>1</strong><span>ubicación actual por cubierta</span></li>
                <li><strong>100%</strong><span>de movimientos auditados</span></li>
                <li><strong>5</strong><span>roles con permisos claros</span></li>
                <li><strong>Web + app</strong><span>para oficina y taller</span></li>
            </ul>
        </section>

        <section id="funciones" class="landing-section">
            <p class="landing-kicker">Funciones</p>
            <h2>Todo lo que pasa con un neumático, en un solo lugar</h2>
            <div class="landing-grid">
                <article class="landing-card">
                    <h3>Planilla por unidad</h3>
                    <p>Chasis visual con ubicaciones numeradas y auxilio. Montar, cambiar, rotar o retirar con un toque.</p>
                </article>
                <article class="landing-card">
                    <h3>Stock y compras</h3>
                    <p>Ingreso de compras con numeración consecutiva. Todo retiro pasa por stock, aunque sea de pasaje.</p>
                </article>
                <article class="landing-card">
                    <h3>Ficha de cubierta</h3>
                    <p>Historial completo: unidades, km por periodo, mediciones de profundidad, incidencias y vidas.</p>
                </article>
                <article class="landing-card">
                    <h3>Acoples y odómetros</h3>
                    <p>Tractor + semi, tanque o batea. Los km del acoplado salen del tractor mientras están unidos.</p>
                </article>
                <article class="landing-card">
                    <h3>Recapado y bajas</h3>
                    <p>El recapado abre una vida nueva. Cola de próximas a baja según desgaste y profundidad.</p>
                </article>
                <article class="landing-card">
                    <h3>Informes</h3>
                    <p>Km por cubierta, consumo, informe semanal, incidencias, costo por km y movimientos.</p>
                </article>
                <article class="landing-card">
                    <h3>QR y órdenes de trabajo</h3>
                    <p>Escaneá la cubierta para abrir su ficha y organizá el trabajo de taller.</p>
                </article>
                <article class="landing-card">
                    <h3>App móvil</h3>
                    <p>El operario carga odómetros y movimientos desde el celular, junto a la unidad.</p>
                </article>
            </div>
        </section>

        <section id="roles" class="landing-section">
            <p class="landing-kicker">Roles</p>
            <h2>Cada persona ve y hace lo que le corresponde</h2>
            <ol class="landing-roles">
                <li><strong>Consulta</strong><span>Tablero, planillas, stock, fichas y reportes. No opera.</span></li>
                <li><strong>Operario</strong><span>La planilla del día: montar, cambiar, rotar, medir, pinchadura y compras.</span></li>
                <li><strong>Logística</strong><span>Además acopla, desacopla y corrige odómetros.</span></li>
                <li><strong>Jefe de sector</strong><span>Además baja, recapado y configuración de ejes.</span></li>
                <li><strong>Administrador</strong><span>Todo, más catálogos, usuarios y datos maestros.</span></li>
            </ol>
        </section>

        <section id="reglas" class="landing-section">
            <p class="landing-kicker">Reglas</p>
            <h2>Lo que el sistema no negocia</h2>
            <ul class="landing-rules">
                <li>Una cubierta, una ubicación actual.</li>
                <li>Todo retiro pasa por stock.</li>
                <li>El recapado abre vida nueva; la reparación no.</li>
                <li>El auxilio no suma km.</li>
                <li>La rotación no cierra el periodo de km.</li>
                <li>Los eventos históricos son inmutables.</li>
                <li>Un acoplado sin tractor no registra km.</li>
                <li>No se da de baja una cubierta colocada.</li>
            </ul>
        </section>

        <section class="landing-cta">
            <h2>¿Ya tenés usuario?</h2>
            <p>Entrá al tablero para ver el estado de la flota.</p>
            <a href="{{ route('login') }}" class="btn btn-primary">Ingresar</a>
        </section>
    </main>

    <footer class="landing-footer">
        <span>© {{ date('Y') }} {{ config('app.name', 'Rodante') }} · Gestión inteligente de neumáticos para flotas</span>
    </footer>
</body>
</html>
