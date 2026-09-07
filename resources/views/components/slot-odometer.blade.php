@props(['lastKm' => null, 'id' => null])
<label class="field field--km">
    <span>Km de la unidad <em class="font-normal text-[var(--muted)]">(opcional)</em></span>
    <input
        name="odometer"
        type="number"
        min="{{ $lastKm ?? 0 }}"
        class="inp"
        inputmode="numeric"
        autocomplete="off"
        @if($id) id="{{ $id }}" @endif
        placeholder="{{ $lastKm !== null ? number_format($lastKm, 0, '', '') : 'Dejar vacío si logística lo carga después' }}"
    >
    @if($lastKm !== null)
        <span class="hint">Última lectura: {{ number_format($lastKm) }} km. Si lo dejás vacío, se usa esa lectura como provisional y logística la corrige después.</span>
    @else
        <span class="hint">Podés dejarlo vacío: el cambio se registra igual y logística completa el km luego.</span>
    @endif
</label>
