@props(['tire' => null, 'position', 'prefix'])

@php
    $code = $position->sheetCode($prefix);
    $tone = $tire?->treadTone() ?? 'empty';
    $mounted = $tire?->openAssignment?->started_at?->format('d/m/Y');
@endphp

@if($tire)
    <a href="{{ route('tires.show', $tire) }}"
       class="tire-box tire-box--{{ $tone }}"
       title="{{ $code }} · {{ $tire->displayName() }} · {{ $tire->size->code }} · {{ $tire->current_tread_min ? $tire->current_tread_min.' mm' : 's/med' }} · {{ number_format($tire->accumulated_km) }} km">
        <small>{{ $code }}</small>
        <strong>{{ $tire->individual_number }}</strong>
        <span class="tire-tip">
            <b>{{ $tire->displayName() }}</b>
            {{ $tire->size->displayName() }}<br>
            {{ $tire->current_tread_min ? $tire->current_tread_min.' mm' : 'Sin medición' }}
            · {{ number_format($tire->accumulated_km) }} km<br>
            @if($mounted)Montaje {{ $mounted }}@endif
        </span>
    </a>
@else
    <div class="tire-box tire-box--empty" title="{{ $code }} vacío">
        <small>{{ $code }}</small>
        <strong>—</strong>
    </div>
@endif
