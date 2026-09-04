@extends('layouts.print')
@section('title', $tire->displayName())
@section('back', route('tires.show', $tire))
@section('reference', 'CUB-'.$tire->id)
@section('document', 'Ficha de cubierta')
@section('subtitle', $tire->fullName().' · '.($tire->size?->displayName() ?? ''))
@section('code', 'Nº '.$tire->individual_number)
@section('body')
<section class="section">
    <h2>Identificación</h2>
    <div class="facts">
        <div><span>Número individual</span><div class="mono">{{ $tire->individual_number }}</div></div>
        <div><span>Estado</span><div>{{ $tire->status->label() }}</div></div>
        <div><span>Marca / modelo</span><div>{{ $tire->fullName() }}</div></div>
        <div><span>Condición</span><div>{{ $tire->condition->label() }}</div></div>
        <div><span>Medida</span><div>{{ $tire->size?->displayName() ?? '—' }}</div></div>
        <div><span>Vida</span><div>{{ $tire->currentLifecycle?->life_number ?? 1 }}</div></div>
        <div><span>Km acumulados</span><div class="mono">{{ number_format($tire->accumulated_km) }}</div></div>
        <div><span>Profundidad mín.</span><div>{{ $tire->current_tread_min ? $tire->current_tread_min.' mm' : '—' }}</div></div>
        <div><span>Ubicación</span><div>
            @if($tire->currentLocation?->unit)
                {{ $tire->currentLocation->unit->plate }}{{ $tire->currentLocation->position ? ' · '.$tire->currentLocation->position->name : '' }}
            @else
                {{ $tire->currentLocation?->location_kind?->label() ?? $tire->status->label() }}
            @endif
        </div></div>
        <div><span>Alta</span><div class="mono">{{ $tire->purchased_at?->format('d/m/Y') ?? '—' }}</div></div>
        <div><span>DOT</span><div class="mono">{{ $tire->dot ?: '—' }}</div></div>
        @if($tire->manufactureLabel())
            <div><span>Fabricación (DOT)</span><div>{{ $tire->manufactureLabel() }}</div></div>
        @endif
    </div>
</section>
@if($tire->numberChanges->isNotEmpty())
<section class="section">
    <h2>Historial de número individual</h2>
    <table>
        <thead>
            <tr>
                <th>De</th>
                <th>A</th>
                <th>Quién</th>
                <th>Fecha</th>
                <th>Motivo</th>
            </tr>
        </thead>
        <tbody>
        @foreach($tire->numberChanges as $change)
            <tr>
                <td class="mono">{{ $change->from_number }}</td>
                <td class="mono">{{ $change->to_number }}</td>
                <td>{{ $change->user?->name }}</td>
                <td class="mono">{{ $change->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                <td>{{ $change->reason }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endif
@endsection
