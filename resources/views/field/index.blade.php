@extends('layouts.app')
@section('title', 'Buscar cubierta')
@section('content')
<x-page-header
    kicker="Día a día"
    title="Buscar cubierta"
    subtitle="Escribí el número o escaneá el QR. Pensado para playa o camión, desde el celular."
/>

<x-help-callout title="¿Para qué es esto?">
    <p class="mb-0">Es el acceso rápido cuando estás junto a la cubierta: buscás el número o el QR y entrás a la ficha, la planilla o devolver a stock, sin recorrer todo el menú.</p>
</x-help-callout>

<form method="GET" action="{{ route('field.index') }}" class="panel max-w-xl">
    <div class="panel__body space-y-4">
        <label class="field">
            <span>Número de cubierta o código QR</span>
            <input
                class="inp"
                name="q"
                value="{{ $term }}"
                inputmode="numeric"
                autocomplete="off"
                autofocus
                placeholder="Ej. 30371"
                style="min-height:3.2rem;font-size:1.25rem"
                aria-label="Número de cubierta o código QR"
                data-field-q
            >
        </label>
        @if($miss)
            <p class="field-error" role="alert">No hay una cubierta con ese dato en tu empresa.</p>
        @endif

        <div class="field-scan">
            <div class="field-scan__actions">
                <button type="button" class="btn btn-dark" data-field-scan-start>Escanear QR con cámara</button>
                <button type="button" class="btn btn-ghost" data-field-scan-stop hidden>Cerrar cámara</button>
            </div>
            <div data-field-scan-panel hidden>
                <video class="field-scan__video" data-field-scan-video playsinline muted></video>
            </div>
            <p class="hint mb-0" data-field-scan-status aria-live="polite">Si el celular no permite cámara acá, escribí el número o pegá el código del QR.</p>
        </div>

        <button class="btn btn-primary w-full" style="min-height:3rem">Buscar</button>
    </div>
</form>
@endsection
