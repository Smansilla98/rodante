@extends('layouts.print')
@section('title', 'Informe semanal '.$report['from']->format('d/m/Y').' – '.$report['to']->format('d/m/Y'))
@section('back', route('reports.weekly', ['from' => $report['from']->toDateString(), 'to' => $report['to']->toDateString()]))
@section('reference', 'SEM-'.$report['from']->format('Ymd').'-'.$report['to']->format('Ymd'))
@section('document', 'Informe semanal de neumáticos')
@section('subtitle', 'Del '.$report['from']->format('d/m/Y').' al '.$report['to']->format('d/m/Y'))
@section('code', 'Stock al '.$report['generated_at']->format('d/m/Y H:i'))
@section('body')
<section class="section">
    <h2>Stock al momento</h2>
    <table>
        <thead>
            <tr><th>Estado</th><th>Cantidad</th></tr>
        </thead>
        <tbody>
        @foreach($report['stock_counts'] as $row)
            <tr>
                <td>{{ $row['status'] }}</td>
                <td class="mono">{{ $row['total'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p class="mt">En stock listo para instalar: <strong class="mono">{{ $report['stock_total'] }}</strong></p>
</section>

<section class="section">
    <h2>Movimientos de la semana</h2>
    @if(count($report['movements']) === 0)
        <p>No hubo movimientos en el período.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Momento</th>
                    <th>Cubierta</th>
                    <th>Salió</th>
                    <th>Entró</th>
                    <th>Unidad</th>
                    <th>Tipo</th>
                </tr>
            </thead>
            <tbody>
            @foreach($report['movements'] as $row)
                <tr>
                    <td class="mono">{{ $row['moment'] }}</td>
                    <td>{{ $row['tire'] }}</td>
                    <td>{{ $row['left'] }}</td>
                    <td>{{ $row['entered'] }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td>{{ $row['type'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</section>

@if($report['purchases']->isNotEmpty())
<section class="section">
    <h2>Compras</h2>
    <table>
        <thead>
            <tr><th>Fecha</th><th>Nº</th><th>Proveedor</th><th>Base</th><th>Cubiertas</th></tr>
        </thead>
        <tbody>
        @foreach($report['purchases'] as $purchase)
            <tr>
                <td>{{ ($purchase->confirmed_at ?? $purchase->purchased_at)?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $purchase->number ?? '#'.$purchase->id }}</td>
                <td>{{ $purchase->supplier?->name ?? '—' }}</td>
                <td>{{ $purchase->base?->name ?? '—' }}</td>
                <td class="mono">{{ $purchase->items->sum('quantity') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endif

@if(count($report['retirements']) > 0)
<section class="section">
    <h2>Bajas</h2>
    <table>
        <thead>
            <tr><th>Momento</th><th>Cubierta</th><th>Motivo</th><th>Notas</th></tr>
        </thead>
        <tbody>
        @foreach($report['retirements'] as $row)
            <tr>
                <td class="mono">{{ $row['moment'] }}</td>
                <td>{{ $row['tire'] }}</td>
                <td>{{ $row['reason'] ?? '—' }}</td>
                <td>{{ $row['notes'] ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endif
@endsection
