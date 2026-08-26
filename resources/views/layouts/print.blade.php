<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name', 'Rodante') }}</title>
    <style>
        :root {
            --ink: #12151c;
            --muted: #5b6475;
            --line: #d5dae4;
            --brand: #c8102e;
            --paper: #fff;
            --soft: #f4f6fa;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: "Segoe UI", system-ui, sans-serif;
            color: var(--ink);
            background: #e8ebf2;
            font-size: 13px;
            line-height: 1.45;
        }
        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            background: var(--paper);
            padding: 14mm 16mm 16mm;
            box-shadow: 0 8px 28px rgb(18 21 28 / 0.12);
        }
        .doc-head {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px 24px;
            align-items: start;
            border-bottom: 3px solid var(--brand);
            padding-bottom: 12px;
        }
        .brand {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .brand img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }
        .brand-name {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.1;
            margin: 0;
        }
        .brand-tag {
            margin: 2px 0 0;
            color: var(--muted);
            font-size: 11px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .company {
            margin: 8px 0 0;
            font-size: 13px;
            font-weight: 600;
        }
        .meta {
            min-width: 210px;
            border: 1px solid var(--line);
            background: var(--soft);
            padding: 8px 10px;
            font-size: 11px;
        }
        .meta strong { display: block; font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--brand); margin-bottom: 6px; }
        .meta dl { display: grid; grid-template-columns: auto 1fr; gap: 3px 10px; margin: 0; }
        .meta dt { color: var(--muted); }
        .meta dd { margin: 0; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; }
        .doc-type {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 12px;
        }
        .doc-type h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
        }
        .doc-type .code {
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            color: var(--brand);
            font-size: 15px;
        }
        .lead { margin: 6px 0 0; color: var(--muted); }
        .section { margin-top: 18px; }
        .section h2 {
            margin: 0 0 8px;
            font-size: 11px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--brand);
            border-bottom: 1px solid var(--line);
            padding-bottom: 4px;
        }
        .facts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border: 1px solid var(--line);
        }
        .facts div {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 8px;
            padding: 7px 10px;
            border-bottom: 1px solid var(--line);
            border-right: 1px solid var(--line);
        }
        .facts div:nth-last-child(-n+2) { border-bottom: 0; }
        .facts span { color: var(--muted); }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid var(--line); padding: 7px 8px; text-align: left; font-size: 12px; }
        th { background: var(--soft); font-size: 10px; letter-spacing: 0.06em; text-transform: uppercase; }
        .mono { font-variant-numeric: tabular-nums; }
        .sign {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 24px;
            margin-top: 36px;
        }
        .sign p {
            margin: 0;
            border-top: 1px solid var(--ink);
            padding-top: 6px;
            font-size: 11px;
            color: var(--muted);
            text-align: center;
        }
        .doc-foot {
            margin-top: 28px;
            padding-top: 8px;
            border-top: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            gap: 12px;
            color: var(--muted);
            font-size: 10px;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            background: #12151c;
        }
        .toolbar button, .toolbar a {
            appearance: none;
            border: 0;
            background: #c8102e;
            color: #fff;
            font: inherit;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
        }
        .toolbar a { background: #2a3344; }
        .forecast-copy { margin: 0 0 10px; }
        .mt { margin-top: 10px; }
        .hist {
            border: 1px solid var(--line);
            border-bottom: 0;
            padding: 8px 10px;
        }
        .hist:last-of-type { border-bottom: 1px solid var(--line); }
        .hist__when { color: var(--muted); font-size: 11px; margin-bottom: 2px; }
        .hist ul { margin: 6px 0 0; padding-left: 18px; }
        .photos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .photos img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border: 1px solid var(--line);
        }
        .photos figcaption { color: var(--muted); font-size: 10px; margin-top: 4px; }
        @media screen and (max-width: 768px) {
            body { background: #e8ebf2; }
            .sheet {
                width: auto;
                min-height: 0;
                margin: 8px;
                padding: 16px;
            }
            .facts { grid-template-columns: 1fr; }
            .facts div { grid-template-columns: 1fr; }
            .sign { grid-template-columns: 1fr; gap: 28px; }
            .photos { grid-template-columns: 1fr; }
            .toolbar { gap: 10px; padding: 12px; }
            .toolbar button, .toolbar a { min-height: 44px; padding: 10px 16px; }
            .doc-head { grid-template-columns: 1fr; }
        }
        @page {
            size: A4;
            margin: 12mm;
        }
        @media print {
            body { background: #fff; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .toolbar { display: none; }
            .facts div:nth-child(odd) { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Imprimir / guardar PDF</button>
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
</body>
</html>
