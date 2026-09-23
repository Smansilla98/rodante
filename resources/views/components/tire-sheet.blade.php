@props(['units', 'current', 'interactive' => false])

<section class="tire-sheet">
    <div class="tire-map">
        @foreach($units as $sheetUnit)
            <x-tire-sheet-unit
                :unit="$sheetUnit"
                :current="$current"
                :layout="$sheetUnit->tireLayout()"
                :diagnostics="$sheetUnit->tireDiagnostics()"
                :interactive="$interactive"
            />
        @endforeach
    </div>

    <details class="tire-legend">
        <summary>Ver / ocultar convenciones</summary>
        <div class="tire-legend__grid">
            <span><i class="lg lg--llanta"></i> Llanta</span>
            <span><i class="lg lg--warn"></i> Prof. mín. cerca</span>
            <span><i class="lg lg--critical"></i> Prof. mín. alcanzada</span>
            <span><i class="lg lg--empty"></i> Vacío</span>
            <span><i class="ax ax--dir"></i> Eje dirección</span>
            <span><i class="ax ax--drive"></i> Eje tracción</span>
            <span><i class="ax ax--drag"></i> Eje muerto</span>
            <span><i class="ax ax--lift"></i> Eje levantable</span>
            <span><span class="tire-box__flag" style="position: static; display: inline-block;" aria-hidden="true">!</span> Rotación o alineación sugerida</span>
        </div>
    </details>
</section>
