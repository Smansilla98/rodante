@props([
    'editing' => false,
    'cancel' => null,
    'addLabel' => 'Agregar',
    'saveLabel' => 'Guardar',
])
<div class="abm-actions">
    <button type="submit" class="btn btn-primary">{{ $editing ? $saveLabel : $addLabel }}</button>
    @if($editing && $cancel)
        <a href="{{ $cancel }}" class="btn btn-ghost">Cancelar</a>
    @endif
</div>
