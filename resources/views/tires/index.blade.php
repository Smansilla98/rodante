@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', request()->routeIs('tires.stock') ? 'Stock' : 'Neumáticos')
@section('content')
<x-page-header
    kicker="Operación"
    :title="request()->routeIs('tires.stock') ? 'Stock' : 'Neumáticos'"
    :subtitle="request()->routeIs('tires.stock') ? 'Cubiertas disponibles para instalar.' : 'Buscá por modelo o número individual.'"
>
    <x-slot:actions>
        <a href="{{ route('purchases.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> Nueva compra</a>
    </x-slot:actions>
</x-page-header>

<x-panel :flush="true">
    <x-slot:toolbar>
        <form class="toolbar" method="GET">
            <input name="q" value="{{ request('q') }}" placeholder="FH:01 o 30363">
            <select name="brand_id">
                <option value="">Marca</option>
                @foreach($brands as $brand)<option value="{{ $brand->id }}" @selected(request('brand_id')==$brand->id)>{{ $brand->name }}</option>@endforeach
            </select>
            <select name="model_id">
                <option value="">Modelo</option>
                @foreach($models as $model)<option value="{{ $model->id }}" @selected(request('model_id')==$model->id)>{{ $model->code }}</option>@endforeach
            </select>
            <select name="size_id">
                <option value="">Medida</option>
                @foreach($sizes as $size)<option value="{{ $size->id }}" @selected(request('size_id')==$size->id)>{{ $size->displayName() }}</option>@endforeach
            </select>
            @unless(request()->routeIs('tires.stock'))
                <select name="status">
                    <option value="">Estado</option>
                    @foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status')==$status->value)>{{ $status->label() }}</option>@endforeach
                </select>
            @endunless
            <button class="btn btn-dark btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Cubierta</th>
                    <th>Medida</th>
                    <th>Estado</th>
                    <th>Condición</th>
                    <th>Ubicación</th>
                    <th class="text-right">Km</th>
                </tr>
            </thead>
            <tbody>
            @forelse($tires as $tire)
                <tr>
                    <td>
                        <a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a>
                        <div class="text-xs text-slate-500">{{ $tire->brand->name }}</div>
                    </td>
                    <td>{{ $tire->size->displayName() }}</td>
                    <td><x-status :tone="$tire->status->tone()">{{ $tire->status->label() }}</x-status></td>
                    <td>{{ $tire->condition->label() }}</td>
                    <td>
                        @if($tire->currentLocation?->unit)
                            <a href="{{ route('units.show', $tire->currentLocation->unit) }}">{{ $tire->currentLocation->unit->plate }}</a>
                            <span class="text-slate-500">· {{ $tire->currentLocation->position?->name }}</span>
                        @else
                            {{ $tire->currentLocation?->base?->name ?? '—' }}
                        @endif
                    </td>
                    <td class="text-right mono">{{ number_format($tire->accumulated_km) }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty title="No hay cubiertas con ese filtro" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pager">{{ $tires->links() }}</div>
</x-panel>
@endsection
