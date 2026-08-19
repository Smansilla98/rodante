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
    if ($small) {
        $classes[] = 'table-sm';
    }
@endphp
<div {{ $attributes->class('table-responsive') }}>
    <table class="{{ implode(' ', $classes) }}">
        {{ $slot }}
    </table>
</div>
