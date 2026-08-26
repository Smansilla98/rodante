@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Telemetría')
@section('content')
<x-page-header
    kicker="Consulta"
    title="Telemetría de operación"
    subtitle="Eventos de los últimos {{ $days }} días en esta empresa: ingresos, campo, planilla, mediciones, bajas e informes."
/>

<div class="kpi-grid mb-6">
    @foreach(['auth.login' => 'Ingresos', 'field.identify' => 'Campo', 'tire.operation' => 'Planilla', 'tire.measured' => 'Mediciones', 'tire.retired' => 'Bajas', 'tire.life_report' => 'Informes'] as $key => $label)
        <div class="kpi">
            <div class="kpi__l">{{ $label }}</div>
            <div class="kpi__v">{{ number_format($totals[$key] ?? 0) }}</div>
        </div>
    @endforeach
</div>
<p class="hint mb-4">
    Origen: web {{ $sources['web'] ?? 0 }} · app {{ $sources['pwa'] ?? 0 }} · API {{ $sources['api'] ?? 0 }}.
    No se envía a un proveedor externo: queda en la base de la empresa.
</p>

<x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Quién</th>
                <th scope="col">Evento</th>
                <th scope="col">Canal</th>
                <th scope="col">Detalle</th>
            </tr>
        </thead>
        <tbody>
        @forelse($events as $event)
            <tr>
                <td class="mono">{{ $event->created_at?->format('d/m/Y H:i') }}</td>
                <td>{{ $event->user?->name ?? 'Sistema' }}</td>
                <td>{{ $event->label() }}</td>
                <td>{{ $event->sourceLabel() }}</td>
                <td class="hint">{{ $event->context['tire'] ?? $event->path }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty title="Todavía no hay eventos" text="Cuando alguien opere, identifique o dé de baja, aparece acá." /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $events->links() }}</div>
</x-panel>
@endsection
