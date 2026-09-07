<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name', 'Rodante') }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('css/print.css') }}">
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" id="btnPrint">Imprimir / guardar PDF</button>
        <a href="@yield('back')">Volver</a>
    </div>
    <article class="sheet">
        @php
            $issued = $issuedAt ?? now();
            $app = config('app.name', 'Rodante');
            $companyName = $companyName ?? auth()->user()?->company?->name;
            $actor = $printedBy ?? auth()->user()?->name;
        @endphp
        <header class="doc-head">
            <div>
                <div class="brand">
                    <img src="{{ asset('brand/rodante-app-icon.png') }}" alt="{{ $app }}">
                    <div>
                        <p class="brand-name">{{ $app }}</p>
                        <p class="brand-tag">Gestión inteligente de neumáticos</p>
                    </div>
                </div>
                @if($companyName)
                    <p class="company">{{ $companyName }}</p>
                @endif
            </div>
            <aside class="meta" aria-label="Datos de emisión interna">
                <strong>Documento interno</strong>
                <dl>
                    <dt>Emitido</dt><dd>{{ $issued->timezone(config('app.timezone'))->format('d/m/Y') }}</dd>
                    <dt>Hora</dt><dd>{{ $issued->timezone(config('app.timezone'))->format('H:i') }}</dd>
                    <dt>Generado por</dt><dd>{{ $actor ?: '—' }}</dd>
                    <dt>Ref.</dt><dd>@yield('reference')</dd>
                </dl>
            </aside>
            <div class="doc-type">
                <div>
                    <h1>@yield('document')</h1>
                    <p class="lead">@yield('subtitle')</p>
                </div>
                <div class="code">@yield('code')</div>
            </div>
        </header>
        @yield('body')
        <div class="sign">
            <p>Responsable de flota</p>
            <p>Operario / taller</p>
            <p>Control interno</p>
        </div>
        <footer class="doc-foot">
            <span>Uso interno de {{ $app }}. No es comprobante fiscal.</span>
            <span>Emisión {{ $issued->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
        </footer>
    </article>
    <script src="{{ asset('js/print.js') }}"></script>
</body>
</html>
