@props([
    'action',
    'confirm' => '¿Eliminar este registro? Esta acción no se puede deshacer.',
    'label' => 'Eliminar',
])
<form method="POST" action="{{ $action }}" class="inline" onsubmit="return confirm(@js($confirm))">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-ghost btn-sm">{{ $label }}</button>
</form>
