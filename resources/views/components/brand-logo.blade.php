@props([
    'variant' => 'sidebar', // sidebar|auth|mark
])

@php
    $alt = config('app.name', 'Rodanta');
@endphp

@if ($variant === 'auth')
    <img
        src="{{ asset('brand/rodanta-logo.png') }}"
        alt="{{ $alt }}"
        {{ $attributes->class('brand-logo brand-logo--auth') }}
    >
@elseif ($variant === 'mark')
    <img
        src="{{ asset('brand/rodanta-app-icon.png') }}"
        alt=""
        aria-hidden="true"
        {{ $attributes->class('sb-mark-img') }}
    >
@else
    <span {{ $attributes->class('sb-brand-lockup') }}>
        <img
            src="{{ asset('brand/rodanta-app-icon.png') }}"
            alt=""
            aria-hidden="true"
            class="sb-mark-img"
        >
        <span>
            <span class="sb-brand-t">Rodanta</span>
            <span class="sb-brand-k">Gestión inteligente de neumáticos</span>
        </span>
    </span>
@endif
