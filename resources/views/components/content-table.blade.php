@props([
    'striped' => true,
    'hover' => true,
    'small' => false,
])
@php
    $classes = ['table', 'mb-0'];
    if ($striped) {
        $classes[] = 'table-striped';
    }
    if ($hover) {
        $classes[] = 'table-hover';
    }
@endphp
<div {{ $attributes->class('table-responsive') }}>
    <table class="{{ implode(' ', $classes) }}">
        {{ $slot }}
    </table>
</div>
