@props([
    'variant' => 'sidebar', // sidebar|auth|mark
])

@php
    $alt = config('app.name', 'Rodante');
@endphp

@if ($variant === 'auth')
    <img
        src="{{ asset('brand/rodante-app-icon.png') }}"
        alt="{{ $alt }}"
        {{ $attributes->class('brand-logo brand-logo--auth') }}
    >
@elseif ($variant === 'mark')
    <img
        src="{{ asset('brand/rodante-app-icon.png') }}"
        alt=""
        aria-hidden="true"
        {{ $attributes->class('sb-mark-img') }}
    >
@else
    <span {{ $attributes->class('sb-brand-lockup') }}>
        <img
            src="{{ asset('brand/rodante-app-icon.png') }}"
            alt=""
            aria-hidden="true"
            class="sb-mark-img"
        >
        <span>
            <span class="sb-brand-t">Rodante</span>
            <span class="sb-brand-k">Gestión inteligente de neumáticos</span>
        </span>
    </span>
@endif
