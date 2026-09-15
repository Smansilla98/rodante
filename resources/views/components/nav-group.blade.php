@props([
    'title',
    'collapsed' => false,
    'persist' => true,
])
@php
    $id = 'sb-'.\Illuminate\Support\Str::slug($title);
@endphp
<div
    class="sb-group"
    data-sb-group="{{ $id }}"
    data-sb-persist="{{ $persist ? '1' : '0' }}"
    @if($collapsed) data-sb-default-collapsed="1" @endif
>
    <button
        type="button"
        class="sb-lbl sb-lbl--toggle"
        data-sb-toggle
        aria-expanded="{{ $collapsed ? 'false' : 'true' }}"
        aria-controls="{{ $id }}-links"
        id="{{ $id }}-btn"
    >
        <span>{{ $title }}</span>
        <span class="sb-lbl__chev" aria-hidden="true"></span>
    </button>
    <div class="sb-group__links" id="{{ $id }}-links" role="group" aria-labelledby="{{ $id }}-btn">
        {{ $slot }}
    </div>
</div>
