@extends('layouts.app')
@section('kicker', 'Ayuda')
@section('title', 'Manual de uso')
@section('content')
<x-page-header
    kicker="Ayuda"
    title="Manual de uso"
    subtitle="Guía completa del sistema. El mismo texto está en docs/manual-de-uso.md para imprimir o enviar."
>
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('help.index') }}">Qué hace cada rol</a>
        <a class="btn btn-ghost" href="{{ route('starting-point.index') }}">Punto de partida</a>
        <button class="btn btn-dark no-print" type="button" id="btnPrint">Imprimir</button>
    </x-slot:actions>
</x-page-header>

<article class="manual-prose">
    {!! $html !!}
</article>
@endsection
