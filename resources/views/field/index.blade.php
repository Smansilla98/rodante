@extends('layouts.app')
@section('title', 'Campo')
@section('content')
<x-page-header kicker="Operación" title="Campo" subtitle="Escribí el número o pegá el token del QR." />
<form method="GET" action="{{ route('field.index') }}" class="panel max-w-xl">
    <div class="panel__body space-y-4">
        <label class="field">
            <span>Número o QR</span>
            <input class="inp" name="q" value="{{ $term }}" inputmode="numeric" autocomplete="off" autofocus style="min-height:3.2rem;font-size:1.25rem">
        </label>
        @if($miss)
            <p class="field-error" role="alert">No hay una cubierta con ese dato en tu empresa.</p>
        @endif
        <button class="btn btn-primary w-full" style="min-height:3rem">Identificar</button>
    </div>
</form>
@endsection
