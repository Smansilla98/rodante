@props(['href', 'label' => 'Exportar CSV'])

<a href="{{ $href }}" class="btn btn-ghost" {{ $attributes }}>
    <x-icon name="grid" class="w-4 h-4" /> {{ $label }}
</a>
