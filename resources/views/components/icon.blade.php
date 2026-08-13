@props(['name', 'class' => 'w-4 h-4'])
@php
    $paths = [
        'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5V21a.75.75 0 0 1-.75.75H14.25V15h-4.5v6.75H3.75A.75.75 0 0 1 3 21V10.5Z"/>',
        'truck' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5h11.25V16.5H3V7.5Zm11.25 3H18l3 3v3h-6.75v-6Zm-8.25 8.25a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10.5 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/>',
        'circle' => '<circle cx="12" cy="12" r="7.25"/><path stroke-linecap="round" d="M12 8.5v3.2l2 1.3"/>',
        'boxes' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 8.25 12 4.5l8.25 3.75L12 12 3.75 8.25Zm0 7.5L12 19.5l8.25-3.75M3.75 12 12 15.75 20.25 12"/>',
        'cart' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h1.7l1.8 10.2h11.1L20 7.5H7.1M8.25 20.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm9 0a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"/>',
        'gauge' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 16.5a8.25 8.25 0 1 1 15 0M12 12.75l3.75-3"/>',
        'chart' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5V6.75M9.75 19.5V10.5M15 19.5V4.5M20.25 19.5v-7.5"/>',
        'alert' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 3.75h.01M10.3 4.8 2.7 18a1.5 1.5 0 0 0 1.3 2.25h16a1.5 1.5 0 0 0 1.3-2.25L13.7 4.8a1.5 1.5 0 0 0-2.6 0Z"/>',
        'shield' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75 19.5 6v6.2c0 4.3-3.1 7.4-7.5 8.55C7.6 19.6 4.5 16.5 4.5 12.2V6L12 3.75Z"/>',
        'tag' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12V4.5H12l8.25 8.25-7.5 7.5L3.75 12Zm5.25-4.5a1.125 1.125 0 1 0 0-2.25 1.125 1.125 0 0 0 0 2.25Z"/>',
        'grid' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 4.5h6v6h-6v-6Zm9 0h6v6h-6v-6Zm-9 9h6v6h-6v-6Zm9 0h6v6h-6v-6Z"/>',
        'ruler' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75 15.75 4.5 19.5 8.25 8.25 19.5 4.5 15.75Zm4.5-4.5 2.25 2.25M11.25 8.25l2.25 2.25"/>',
        'fleet' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 18.75h16.5M5.25 18.75V8.25L12 4.5l6.75 3.75v10.5"/>',
        'pin' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21s6-5.1 6-10.5a6 6 0 1 0-12 0C6 15.9 12 21 12 21Zm0-8.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z"/>',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM4.5 19.5a5.25 5.25 0 0 1 10.5 0M15 13.5a3 3 0 1 0 0-5.25M19.5 19.5a4.5 4.5 0 0 0-3.3-4.35"/>',
        'plus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 5.25v13.5M5.25 12h13.5"/>',
        'search' => '<path stroke-linecap="round" stroke-linejoin="round" d="m16.5 16.5 3.75 3.75M18 11.25a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z"/>',
        'menu' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 7h15M4.5 12h15M4.5 17h15"/>',
        'logout' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 8.25V6A1.5 1.5 0 0 0 14.25 4.5h-7.5A1.5 1.5 0 0 0 5.25 6v12a1.5 1.5 0 0 0 1.5 1.5h7.5A1.5 1.5 0 0 0 15.75 18v-2.25M12 12h9m0 0-2.25-2.25M21 12l-2.25 2.25"/>',
        'back' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>',
        'inbox' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12.75 6 4.5h12l2.25 8.25v6.75H3.75v-6.75Zm0 0h5.1a3.15 3.15 0 0 0 6.3 0h5.1"/>',
    ];
@endphp
<svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'aria-hidden' => 'true']) }} stroke-width="1.8">
    {!! $paths[$name] ?? $paths['circle'] !!}
</svg>
