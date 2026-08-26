@props(['lastKm' => null, 'id' => null])
<label class="field field--km">
    <span>Km de la unidad en esta operación</span>
    <input
        name="odometer"
        type="number"
        min="{{ $lastKm ?? 0 }}"
        class="inp"
        required
        inputmode="numeric"
        autocomplete="off"
        @if($id) id="{{ $id }}" @endif
        placeholder="{{ $lastKm !== null ? number_format($lastKm, 0, '', '') : 'Km del camión ahora' }}"
    >
    @if($lastKm !== null)
        <span class="hint">Última lectura de la unidad: {{ number_format($lastKm) }} km. Vale solo para esta cubierta; las demás no lo heredan hasta que las retires.</span>
    @else
        <span class="hint">Anotá el odómetro del camión en este recambio, como en la orden de gomería.</span>
    @endif
</label>
