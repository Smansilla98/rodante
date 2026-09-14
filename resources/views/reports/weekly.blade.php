@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Informe semanal')
@section('content')
@php
    $period = ['from' => $report['from']->toDateString(), 'to' => $report['to']->toDateString()];
@endphp
<x-page-header
    kicker="Consulta"
    title="Informe semanal"
    subtitle="Stock al momento, movimientos de la semana, compras y bajas. Exportá o enviá por correo."
/>

@if($errors->any())
    <div class="flash flash--bad" role="alert">{{ $errors->first() }}</div>
@endif

<form class="toolbar mb-5" method="GET" action="{{ route('reports.weekly') }}">
    <label class="field">
        <span>Desde</span>
        <input type="date" name="from" value="{{ $report['from']->toDateString() }}" required>
    </label>
    <label class="field">
        <span>Hasta</span>
        <input type="date" name="to" value="{{ $report['to']->toDateString() }}" required>
    </label>
    <button class="btn btn-dark">Actualizar</button>
</form>

<section class="share-panel" aria-label="Compartir o exportar informe">
    <h2 class="share-panel__title">Exportar o enviar</h2>
    <div class="export-actions">
        <a class="btn btn-ghost" href="{{ route('exports.report-weekly-csv', $period) }}" download>
            <x-icon name="grid" class="w-4 h-4" /> CSV
        </a>
        <a class="btn btn-ghost" href="{{ route('exports.report-weekly-excel', $period) }}" download>
            <x-icon name="grid" class="w-4 h-4" /> Excel
        </a>
        <a class="btn btn-ghost" href="{{ route('reports.weekly.pdf', $period) }}" target="_blank" rel="noopener">
            <x-icon name="book" class="w-4 h-4" /> PDF
        </a>
    </div>
    <form method="POST" action="{{ route('reports.weekly.send') }}" data-confirm="¿Enviar el informe al correo indicado?">
        @csrf
        <input type="hidden" name="from" value="{{ $report['from']->toDateString() }}">
        <input type="hidden" name="to" value="{{ $report['to']->toDateString() }}">
        <div class="share-panel__row">
            <label class="field">
                <span>Enviar a</span>
                <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required placeholder="correo@empresa.com" aria-label="Correo destino">
            </label>
        </div>
        <div class="action-bar__submit" style="border:0;padding-top:0">
            <button class="btn btn-primary" type="submit">Enviar por correo</button>
        </div>
    </form>
</section>

<p class="hint mb-6">Redactado el {{ $report['generated_at']->format('d/m/Y H:i') }}. El stock es el de <strong>ahora</strong>, no el histórico de la semana.</p>

<section class="mb-8" aria-labelledby="stockTitle">
    <h2 id="stockTitle" class="text-lg font-bold mb-2">Stock al momento</h2>
    <x-panel :flush="true">
        <x-content-table>
            <thead>
                <tr>
                    <th scope="col">Estado</th>
                    <th scope="col">Cantidad</th>
                </tr>
            </thead>
            <tbody>
            @foreach($report['stock_counts'] as $row)
                <tr>
                    <td>{{ $row['status'] }}</td>
                    <td class="mono">{{ $row['total'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </x-content-table>
    </x-panel>

    <h3 class="text-base font-semibold mt-4 mb-2">Cubiertas en stock ({{ $report['stock_total'] }})</h3>
    <x-panel :flush="true">
        <x-content-table>
            <thead>
                <tr>
                    <th scope="col">Nº</th>
                    <th scope="col">Cubierta</th>
                    <th scope="col">Base</th>
                </tr>
            </thead>
            <tbody>
            @forelse($report['stock_tires'] as $tire)
                <tr>
                    <td class="mono">{{ $tire->individual_number }}</td>
                    <td><a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a></td>
                    <td>{{ $tire->currentLocation?->base?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3"><x-empty title="Sin cubiertas en stock" /></td></tr>
            @endforelse
            </tbody>
        </x-content-table>
    </x-panel>
</section>

<section class="mb-8" aria-labelledby="movTitle">
    <h2 id="movTitle" class="text-lg font-bold mb-2">Movimientos de la semana</h2>
    <p class="hint mb-3">Qué salió, qué entró, en qué unidad y a qué hora.</p>
    <x-panel :flush="true">
        <x-content-table>
            <thead>
                <tr>
                    <th scope="col">Momento</th>
                    <th scope="col">Cubierta</th>
                    <th scope="col">Salió</th>
                    <th scope="col">Entró</th>
                    <th scope="col">Unidad</th>
                    <th scope="col">Tipo</th>
                </tr>
            </thead>
            <tbody>
            @forelse($report['movements'] as $row)
                <tr>
                    <td class="mono whitespace-nowrap">{{ $row['moment'] }}</td>
                    <td>{{ $row['tire'] }}</td>
                    <td>{{ $row['left'] }}</td>
                    <td>{{ $row['entered'] }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td>{{ $row['type'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty title="Sin movimientos en el período" /></td></tr>
            @endforelse
            </tbody>
        </x-content-table>
    </x-panel>
</section>

@if($report['purchases']->isNotEmpty())
<section class="mb-8" aria-labelledby="buyTitle">
    <h2 id="buyTitle" class="text-lg font-bold mb-2">Compras</h2>
    <x-panel :flush="true">
        <x-content-table>
            <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Nº</th>
                    <th scope="col">Proveedor</th>
                    <th scope="col">Base</th>
                    <th scope="col">Cubiertas</th>
                </tr>
            </thead>
            <tbody>
            @foreach($report['purchases'] as $purchase)
                <tr>
                    <td>{{ ($purchase->confirmed_at ?? $purchase->purchased_at)?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        <a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->number ?? '#'.$purchase->id }}</a>
                    </td>
                    <td>{{ $purchase->supplier?->name ?? '—' }}</td>
                    <td>{{ $purchase->base?->name ?? '—' }}</td>
                    <td class="mono">{{ $purchase->items->sum('quantity') }}</td>
                </tr>
            @endforeach
            </tbody>
        </x-content-table>
    </x-panel>
</section>
@endif

@if(count($report['retirements']) > 0)
<section class="mb-8" aria-labelledby="retTitle">
    <h2 id="retTitle" class="text-lg font-bold mb-2">Bajas</h2>
    <x-panel :flush="true">
        <x-content-table>
            <thead>
                <tr>
                    <th scope="col">Momento</th>
                    <th scope="col">Cubierta</th>
                    <th scope="col">Motivo</th>
                    <th scope="col">Notas</th>
                </tr>
            </thead>
            <tbody>
            @foreach($report['retirements'] as $row)
                <tr>
                    <td class="mono whitespace-nowrap">{{ $row['moment'] }}</td>
                    <td>{{ $row['tire'] }}</td>
                    <td>{{ $row['reason'] ?? '—' }}</td>
                    <td>{{ $row['notes'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </x-content-table>
    </x-panel>
</section>
@endif
@endsection
