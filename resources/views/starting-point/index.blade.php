@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', 'Punto de partida')
@section('content')
<x-page-header
    kicker="Operación"
    title="Punto de partida"
    subtitle="Stock real previo al sistema: cubiertas ya compradas y anotadas afuera. No es una compra nueva."
>
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('tires.stock') }}">Ver stock</a>
        <a class="btn btn-ghost" href="{{ route('help.manual') }}">Manual</a>
        <button class="btn btn-dark no-print" type="button" id="btnPrint">Imprimir guía</button>
    </x-slot:actions>
</x-page-header>

@if($canWrite)
    <x-panel class="mb-6">
        <div class="panel__body space-y-4">
            <h2 class="text-lg font-bold">Cargar stock previo (CSV)</h2>
            <p class="hint">
                Una fila = una cubierta que ya existía antes de Rodante. Entra a <strong>stock</strong> de la base elegida,
                sin proveedor ni orden de compra. Las montadas en unidades se cargan después desde la planilla.
            </p>
            <p class="hint mono text-sm">
                Columnas: Numero;Marca;Modelo;Medida;DOT;Km;Condicion
            </p>
            <pre class="hint text-sm overflow-x-auto p-3 rounded-lg bg-black/20 border border-[var(--line)]">Numero;Marca;Modelo;Medida;DOT;Km;Condicion
30360;Pirelli;FH:01;295/80 R22.5;4824;45000;USADA
30361;Pirelli;FH:01;295/80 R22.5;4825;1200;NUEVA_USADA
;Michelin;X Multi D;295/80 R22.5;;0;NUEVA</pre>
            <form method="POST" action="{{ route('starting-point.import-stock') }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-2 lg:grid-cols-4 items-end">
                @csrf
                <label class="field">
                    <span>Archivo CSV</span>
                    <input type="file" name="file" accept=".csv,text/csv" required>
                    <x-field-error name="file" />
                </label>
                <label class="field">
                    <span>Base (depósito)</span>
                    <select name="base_id" required>
                        @foreach($bases as $base)
                            <option value="{{ $base->id }}" @selected((string) old('base_id') === (string) $base->id)>{{ $base->name }}</option>
                        @endforeach
                    </select>
                    <x-field-error name="base_id" />
                </label>
                <label class="field">
                    <span>Fecha de corte</span>
                    <input type="date" name="as_of" required value="{{ old('as_of', now()->toDateString()) }}">
                    <x-field-error name="as_of" />
                </label>
                <button class="btn btn-primary">Importar a stock</button>
            </form>
            <ul class="hint text-sm list-disc pl-5 space-y-1">
                <li>Marca = nombre exacto; Modelo y Medida = <strong>código</strong> del catálogo.</li>
                <li>Condición: <code>NUEVA</code>, <code>NUEVA_USADA</code>, <code>USADA</code> (default), <code>RECAPADA</code>, <code>REPARADA</code>.</li>
                <li>Nº vacío = el sistema asigna el siguiente número libre.</li>
                <li>Compras nuevas posteriores van por <a href="{{ route('purchases.create') }}">Compras</a>.</li>
            </ul>
        </div>
    </x-panel>
@else
    <x-panel class="mb-6">
        <div class="panel__body">
            <p class="hint mb-0">Tu rol es de consulta. La carga de stock previo la hace un operario o superior.</p>
        </div>
    </x-panel>
@endif

<article class="manual-prose">
    {!! $html !!}
</article>

<section class="manual-prose mt-8" aria-labelledby="nomenTitle">
    <h2 id="nomenTitle">Nomenclatura de posiciones</h2>
    <p>Para cuando armes la planilla de cubiertas ya montadas (paso siguiente). Códigos generados desde el catálogo del sistema.</p>

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
