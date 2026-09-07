@props([
    'variant' => 'sidebar', // sidebar|auth|mark
])

@php
    $alt = config('app.name', 'Rodante');
    $src = asset('brand/rodante-app-icon.png');
@endphp

@if ($variant === 'auth')
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        width="176"
        height="176"
        decoding="async"
        {{ $attributes->class('brand-logo brand-logo--auth') }}
    >
@elseif ($variant === 'mark')
    <img
        src="{{ $src }}"
        alt=""
        aria-hidden="true"
        width="40"
        height="40"
        decoding="async"
        {{ $attributes->class('sb-mark-img') }}
    >
@else
    <span {{ $attributes->class('sb-brand-lockup') }}>
        <img
            src="{{ $src }}"
            alt=""
            aria-hidden="true"
            width="40"
            height="40"
            decoding="async"
            class="sb-mark-img"
        >
        <span>
            <span class="sb-brand-t">Rodante</span>
            <span class="sb-brand-k">Gestión inteligente de neumáticos</span>
        </span>
    </span>
@endif
