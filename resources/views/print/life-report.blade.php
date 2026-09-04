@extends('layouts.print')
@section('title', 'Informe de vida '.$tire->displayName())
@section('back', route('tires.show', $tire))
@section('reference', 'VIDA-'.$tire->id)
@section('document', 'Informe de vida de la cubierta')
@section('subtitle', $tire->fullName().' · '.($tire->size?->displayName() ?? ''))
@section('code', 'Nº '.$tire->individual_number)
@section('body')
@php
    $retirePhotos = $photos ?? collect();
    $f = $forecast ?? [];
@endphp
<section class="section">
    <h2>Estado actual</h2>
    <div class="facts">
        <div><span>Número individual</span><div class="mono">{{ $tire->individual_number }}</div></div>
        <div><span>Estado</span><div>{{ $tire->status->label() }}</div></div>
        <div><span>Marca / modelo</span><div>{{ $tire->fullName() }}</div></div>
        <div><span>Condición</span><div>{{ $tire->condition->label() }}</div></div>
        <div><span>Medida</span><div>{{ $tire->size?->displayName() ?? '—' }}</div></div>
        <div><span>Vida</span><div>{{ $tire->currentLifecycle?->life_number ?? 1 }} de {{ $tire->lifecycles->count() ?: 1 }}</div></div>
        <div><span>Km acumulados</span><div class="mono">{{ number_format($tire->accumulated_km) }}</div></div>
        <div><span>Profundidad mín.</span><div>{{ $tire->current_tread_min !== null ? $tire->current_tread_min.' mm' : '—' }}</div></div>
        <div><span>Alta</span><div class="mono">{{ $tire->purchased_at?->format('d/m/Y') ?? '—' }}</div></div>
        <div><span>DOT</span><div class="mono">{{ $tire->dot ?: '—' }}</div></div>
        @if($tire->manufactureLabel())
            <div><span>Fabricación (DOT)</span><div>{{ $tire->manufactureLabel() }}</div></div>
        @endif
        <div><span>Baja</span><div class="mono">{{ $tire->retired_at?->format('d/m/Y') ?? 'En servicio' }}</div></div>
        <div><span>Costo acumulado</span><div class="mono">{{ ($costTotal ?? 0) ? '$ '.number_format($costTotal, 2, ',', '.') : '—' }}</div></div>
        <div><span>$ / km</span><div class="mono">{{ (($costTotal ?? 0) && $tire->accumulated_km) ? number_format($costTotal / $tire->accumulated_km, 4, ',', '.') : '—' }}</div></div>
    </div>
</section>

<section class="section">
    <h2>Pronóstico de desgaste</h2>
    <p class="forecast-copy">{{ $f['narrative'] ?? 'Sin pronóstico.' }}</p>
    <div class="facts">
        <div><span>Profundidad actual</span><div>{{ isset($f['current_mm']) && $f['current_mm'] !== null ? number_format($f['current_mm'], 1, ',', '.').' mm' : 'Sin medición' }}</div></div>
        <div><span>Umbral</span><div>{{ number_format($f['threshold_mm'] ?? 4, 1, ',', '.') }} mm</div></div>
        <div><span>Km estimados a 4 mm</span><div class="mono">{{ isset($f['remaining_km']) && $f['remaining_km'] !== null ? number_format($f['remaining_km']).' km' : '—' }}</div></div>
        <div><span>Desgaste</span><div>{{ isset($f['wear_mm_per_1000km']) ? number_format($f['wear_mm_per_1000km'], 3, ',', '.').' mm / 1.000 km' : '—' }}</div></div>
        <div><span>Origen</span><div>{{ ($f['source'] ?? '') === 'measurements' ? 'Mediciones de esta cubierta' : 'Estimación de catálogo' }}</div></div>
        <div><span>Confianza</span><div>{{ match($f['confidence'] ?? 'low') { 'high' => 'Alta', 'medium' => 'Media', default => 'Baja' } }}</div></div>
    </div>
    @if(!empty($f['zones']))
        <table class="mt">
            <thead>
                <tr>
                    <th>Zona</th>
                    <th>mm</th>
                    <th>Km a 4 mm</th>
                </tr>
            </thead>
            <tbody>
            @foreach($f['zones'] as $zone)
                <tr>
                    <td>{{ $zone['name'] }}</td>
                    <td class="mono">{{ number_format($zone['mm'], 1, ',', '.') }}</td>
                    <td class="mono">{{ $zone['remaining_km'] !== null ? number_format($zone['remaining_km']) : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</section>

<section class="section">
    <h2>Vidas</h2>
    <table>
        <thead>
            <tr>
                <th>Vida</th>
                <th>Inicio</th>
                <th>Cierre</th>
                <th>Km</th>
                <th>Origen</th>
            </tr>
        </thead>
        <tbody>
        @forelse($tire->lifecycles->sortBy('life_number') as $life)
            <tr>
                <td class="mono">{{ $life->life_number }}</td>
                <td class="mono">{{ $life->started_at?->format('d/m/Y') }}</td>
                <td class="mono">{{ $life->ended_at?->format('d/m/Y') ?? 'Abierta' }}</td>
                <td class="mono">{{ number_format($life->km_in_life) }}</td>
                <td>{{ $life->started_by }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin vidas registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

<section class="section">
    <h2>Mediciones</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Odómetro</th>
                <th>Zonas</th>
                <th>Quién</th>
            </tr>
        </thead>
        <tbody>
        @forelse($tire->measurements->sortBy('measured_at') as $measurement)
            <tr>
                <td class="mono">{{ $measurement->measured_at?->format('d/m/Y H:i') }}</td>
                <td class="mono">{{ $measurement->odometer !== null ? number_format($measurement->odometer) : '—' }}</td>
                <td>{{ $measurement->readings->map(fn ($r) => ($r->zone?->name ?? 'Zona').': '.$r->millimeters.' mm')->implode(' · ') }}</td>
                <td>{{ $measurement->user?->name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Sin mediciones.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

@if($tire->costEntries->isNotEmpty())
<section class="section">
    <h2>Costos</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
        @foreach($tire->costEntries->sortBy('occurred_at') as $entry)
            <tr>
                <td class="mono">{{ $entry->occurred_at?->format('d/m/Y') }}</td>
                <td>{{ $entry->categoryLabel() }}{{ $entry->notes ? ' · '.$entry->notes : '' }}</td>
                <td class="mono">$ {{ number_format($entry->amount, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endif

<section class="section">
    <h2>Historial completo</h2>
    @forelse($timeline as $item)
        <div class="hist">
            <div class="hist__when">{{ $item['at']?->format('d/m/Y H:i') ?? '—' }}@if(!empty($item['user'])) · {{ $item['user'] }}@endif</div>
            <strong>{{ $item['headline'] }}</strong>
            @if(!empty($item['summary']))
                <div>{{ $item['summary'] }}</div>
            @endif
            @if($item['steps']->isNotEmpty())
                <ul>
                    @foreach($item['steps'] as $step)
                        <li>{{ $step['kind_label'] }}: {{ $step['title'] }}@if(!empty($step['body'])) — {{ $step['body'] }}@endif</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @empty
        <p>Todavía no hay eventos en el historial.</p>
    @endforelse
</section>

@if($retirePhotos->isNotEmpty())
<section class="section">
    <h2>Fotos de baja</h2>
    <div class="photos">
        @foreach($retirePhotos as $photo)
            <figure>
                <img src="{{ route('tires.photos.show', [$tire, $photo]) }}" alt="Foto de baja {{ $photo->original_name }}">
                <figcaption>{{ $photo->captured_at?->format('d/m/Y H:i') }}@if($photo->user) · {{ $photo->user->name }}@endif</figcaption>
            </figure>
        @endforeach
    </div>
</section>
@endif
@endsection
