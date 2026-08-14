@props(['tire' => null, 'position', 'prefix', 'interactive' => false, 'mark' => null])

@php
    $code = $position->sheetCode($prefix);
    $short = $position->is_spare ? 'AUX' : ($position->dual ?: '');
    $tone = $tire?->treadTone() ?? 'empty';
    $role = $position->is_spare ? 'AUXILIO' : $position->axle_role;
    $mounted = $tire?->openAssignment?->started_at?->format('d/m/Y');
    $labelMark = $mark ?: $code;
    $classes = 'tire-box tire-box--'.$tone.' tire-box--role-'.$role.($interactive ? ' tire-box--action' : '').($tire ? '' : ' tire-box--empty').($short ? ' tire-box--'.$short : '');
    $label = $tire
        ? $labelMark.' · '.$code.': '.$tire->displayName().', '.($tire->current_tread_min ? $tire->current_tread_min.' mm' : 'sin medición')
        : $labelMark.' · '.$code.' vacío';
@endphp

@if($interactive)
    <button type="button" class="{{ $classes }}" data-slot="{{ $position->id }}"
        @if($tire) draggable="true" data-tire-id="{{ $tire->id }}" @endif
        data-empty="{{ $tire ? '0' : '1' }}"
        aria-label="{{ $label }}" title="{{ $label }}">
        <strong>{{ $mark ?? ($tire?->individual_number ?? '—') }}</strong>
        @if($tire)
            <span class="tire-tip">
                <b>{{ $tire->displayName() }}</b>
                {{ $tire->size->displayName() }}<br>
                {{ $tire->current_tread_min ? $tire->current_tread_min.' mm' : 'Sin medición' }}
                · {{ number_format($tire->accumulated_km) }} km
                @if($mounted)<br>Montaje {{ $mounted }}@endif
            </span>
        @endif
    </button>
@elseif($tire)
    <a href="{{ route('tires.show', $tire) }}"
       class="{{ $classes }}"
       aria-label="{{ $label }}"
       title="{{ $code }} · {{ $tire->displayName() }}">
        <strong>{{ $mark ?? $tire->individual_number }}</strong>
        <span class="tire-tip">
            <b>{{ $tire->displayName() }}</b>
            {{ $tire->size->displayName() }}<br>
            {{ $tire->current_tread_min ? $tire->current_tread_min.' mm' : 'Sin medición' }}
            · {{ number_format($tire->accumulated_km) }} km<br>
            @if($mounted)Montaje {{ $mounted }}@endif
        </span>
    </a>
@else
    <div class="{{ $classes }}" title="{{ $code }} vacío" aria-label="{{ $code }} vacío">
        <strong>{{ $mark ?? '—' }}</strong>
    </div>
@endif
