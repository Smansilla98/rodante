@extends('layouts.app')
@section('kicker', 'Ayuda')
@section('title', 'Punto de partida')
@section('content')
<x-page-header
    kicker="Ayuda"
    title="Punto de partida"
    subtitle="Carga inicial de flota: orden, plantillas CSV, nomenclatura de posiciones y glosario. El mismo texto está en docs/punto-de-partida.md."
>
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('help.index') }}">Qué hace cada rol</a>
        <a class="btn btn-ghost" href="{{ route('help.manual') }}">Manual de uso</a>
        @if(auth()->user()->role->canWrite())
            <a class="btn btn-primary" href="{{ route('purchases.create') }}">Importar CSV de stock</a>
        @endif
        <button class="btn btn-dark no-print" type="button" id="btnPrint">Imprimir</button>
    </x-slot:actions>
</x-page-header>

<article class="manual-prose">
    {!! $html !!}
</article>

<section class="manual-prose mt-8" aria-labelledby="nomenTitle">
    <h2 id="nomenTitle">Nomenclatura de posiciones</h2>
    <p>Los códigos se generan desde el catálogo de configuraciones del sistema. Usá exactamente estos valores en la columna <strong>Posición</strong> de la planilla de en servicio.</p>

    <h3>Leyenda</h3>
    <ul>
        <li><code>E{n}</code> — número de eje de adelante hacia atrás.</li>
        <li><code>IZQ</code> / <code>DER</code> — lado izquierdo / derecho.</li>
        <li><code>EXT</code> / <code>INT</code> — exterior / interior en eje dual (mellizas).</li>
        <li><code>AUXILIO</code> — rueda de auxilio; no suma kilómetros.</li>
        <li>Roles de eje: <code>DIRECCION</code>, <code>TRACCION</code>, <code>ARRASTRE</code>, <code>DIRECCIONAL</code>, <code>AUXILIO</code>.</li>
    </ul>

    @foreach($positionExamples as $example)
        <h3>{{ $example['code'] }} — {{ $example['name'] }}</h3>
        @if($example['description'] !== '')
            <p>{{ $example['description'] }}</p>
        @endif
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Código</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Eje</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Lado</th>
                        <th scope="col">Dual</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($example['positions'] as $position)
                    <tr>
                        <td><code>{{ $position['code'] }}</code></td>
                        <td>{{ $position['name'] }}</td>
                        <td>{{ $position['axle_number'] ?: '—' }}</td>
                        <td><code>{{ $position['axle_role'] }}</code></td>
                        <td>{{ $position['side'] }}</td>
                        <td>{{ $position['dual'] ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</section>
@endsection
