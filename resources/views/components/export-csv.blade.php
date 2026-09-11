@props(['href', 'label' => 'Exportar CSV'])

<a
    href="{{ $href }}"
    class="btn btn-ghost"
    download
    type="text/csv"
    {{ $attributes }}
>
    <x-icon name="grid" class="w-4 h-4" /> {{ $label }}
</a>
