@props(['title' => 'No hay datos', 'text' => null, 'action' => null, 'href' => null])

<div {{ $attributes->class('empty') }}>
    <x-icon name="inbox" class="w-8 h-8" />
    <div class="empty__t">{{ $title }}</div>
    @if($text)<div class="empty__s">{{ $text }}</div>@endif
    @if($action && $href)
        <a href="{{ $href }}" class="btn btn-primary btn-sm mt-3">{{ $action }}</a>
    @endif
</div>
