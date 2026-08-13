@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Modelos')
@section('content')
<x-page-header kicker="Catálogo" title="Modelos" subtitle="El código es lo que se ve en pantalla: FH:01 Nº30363." />
<x-panel title="Agregar">
    <form method="POST" action="{{ route('models.store') }}" class="grid md:grid-cols-4 gap-2">
        @csrf
        <select name="tire_brand_id" class="inp">@foreach($brands as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach</select>
        <input name="code" class="inp" placeholder="Código (FH:01)" required>
        <input name="name" class="inp" placeholder="Nombre">
        <button class="btn btn-dark">Agregar</button>
    </form>
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    @forelse($models as $model)
        <div class="list-row px-5">
            <span><strong class="mono">{{ $model->code }}</strong> · {{ $model->brand->name }}</span>
            <span class="text-slate-500">{{ $model->name }}</span>
        </div>
    @empty
        <x-empty title="No hay modelos" />
    @endforelse
</x-panel>
@endsection
