@props(['code'])

@php
    $title = match ($code) {
        'longitudinal' => 'Método 1',
        'cruzado' => 'Método 2',
        'diagonal' => 'Método 3',
        default => $code,
    };
@endphp

<svg class="pattern-mini" viewBox="0 0 72 110" aria-hidden="true">
    <title>{{ $title }}</title>
    <rect x="18" y="4" width="36" height="28" rx="12" fill="#c5cad6"/>
    <rect x="14" y="40" width="44" height="52" rx="6" fill="#c5cad6"/>
    <rect x="22" y="14" width="7" height="12" rx="1.2" fill="#111"/>
    <rect x="43" y="14" width="7" height="12" rx="1.2" fill="#111"/>
    <line x1="29" y1="20" x2="43" y2="20" stroke="#111" stroke-width="1.4"/>
    <rect x="16" y="48" width="6" height="11" rx="1" fill="#111"/>
    <rect x="23" y="48" width="6" height="11" rx="1" fill="#111"/>
    <rect x="43" y="48" width="6" height="11" rx="1" fill="#111"/>
    <rect x="50" y="48" width="6" height="11" rx="1" fill="#111"/>
    <line x1="29" y1="53.5" x2="43" y2="53.5" stroke="#111" stroke-width="1.4"/>
    <rect x="16" y="72" width="6" height="11" rx="1" fill="#111"/>
    <rect x="23" y="72" width="6" height="11" rx="1" fill="#111"/>
    <rect x="43" y="72" width="6" height="11" rx="1" fill="#111"/>
    <rect x="50" y="72" width="6" height="11" rx="1" fill="#111"/>
    <line x1="29" y1="77.5" x2="43" y2="77.5" stroke="#111" stroke-width="1.4"/>
    @if($code === 'longitudinal')
        <line x1="25.5" y1="20" x2="46.5" y2="20" stroke="#e11d48" stroke-width="1.6"/>
        <line x1="19" y1="59" x2="19" y2="72" stroke="#e11d48" stroke-width="1.6"/>
        <line x1="26" y1="59" x2="26" y2="72" stroke="#e11d48" stroke-width="1.6"/>
        <line x1="46" y1="59" x2="46" y2="72" stroke="#e11d48" stroke-width="1.6"/>
        <line x1="53" y1="59" x2="53" y2="72" stroke="#e11d48" stroke-width="1.6"/>
    @elseif($code === 'cruzado')
        <line x1="25.5" y1="20" x2="46.5" y2="20" stroke="#e11d48" stroke-width="1.6"/>
        <line x1="19" y1="59" x2="26" y2="72" stroke="#e11d48" stroke-width="1.6"/>
        <line x1="26" y1="59" x2="19" y2="72" stroke="#e11d48" stroke-width="1.6"/>
        <line x1="46" y1="59" x2="53" y2="72" stroke="#e11d48" stroke-width="1.6"/>
        <line x1="53" y1="59" x2="46" y2="72" stroke="#e11d48" stroke-width="1.6"/>
    @else
        <line x1="25.5" y1="20" x2="46.5" y2="20" stroke="#e11d48" stroke-width="1.6"/>
        <line x1="19" y1="59" x2="53" y2="72" stroke="#e11d48" stroke-width="1.6"/>
        <line x1="26" y1="59" x2="46" y2="72" stroke="#e11d48" stroke-width="1.6"/>
        <line x1="46" y1="59" x2="26" y2="72" stroke="#e11d48" stroke-width="1.6"/>
        <line x1="53" y1="59" x2="19" y2="72" stroke="#e11d48" stroke-width="1.6"/>
    @endif
</svg>
