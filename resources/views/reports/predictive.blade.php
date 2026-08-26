@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Predictivo')
@section('content')
<x-page-header
    kicker="Consulta"
    title="Predictivo de desgaste"
    subtitle="Km estimados hasta 4 mm según las mediciones. Si no hay odómetro en las medidas, se usa una estimación de catálogo."
/>
<x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Cubierta</th>
                <th scope="col">mm</th>
                <th scope="col">Km restantes</th>
                <th scope="col">Confianza</th>
                <th scope="col">Informe</th>
            </tr>
        </thead>
        <tbody>
        @forelse($tires as $tire)
            @php $f = $forecasts[$tire->id] ?? []; @endphp
            <tr>
                <td>
                    <a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a>
                    <div class="hint">{{ $f['narrative'] ?? '' }}</div>
                </td>
                <td class="mono">{{ ($f['current_mm'] ?? null) !== null ? number_format($f['current_mm'], 1, ',', '.') : '—' }}</td>
                <td class="mono">{{ ($f['remaining_km'] ?? null) !== null ? number_format($f['remaining_km']) : '—' }}</td>
                <td>{{ match($f['confidence'] ?? 'low') { 'high' => 'Alta', 'medium' => 'Media', default => 'Baja' } }}{{ ($f['source'] ?? '') === 'catalog' ? ' · catálogo' : '' }}</td>
                <td><a class="btn btn-ghost btn-sm" href="{{ route('tires.life-report', $tire) }}" target="_blank">Informe</a></td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty title="Sin cubiertas en servicio" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $tires->links() }}</div>
</x-panel>
@endsection
