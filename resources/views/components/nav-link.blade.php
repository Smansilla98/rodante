@props(['href', 'icon', 'label', 'match' => null])
@php
    $isActive = $match ? request()->routeIs(...(array) $match) : false;
@endphp
<a href="{{ $href }}" @class(['sb-link', 'is-active' => $isActive]) @if($isActive) aria-current="page" @endif>
    <x-icon :name="$icon" class="sb-ico" />
    <span>{{ $label }}</span>
</a>
