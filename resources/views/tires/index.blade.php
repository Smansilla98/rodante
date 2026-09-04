@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', request()->routeIs('tires.stock') ? 'Stock' : 'Neumáticos')
@section('content')
<x-page-header
    kicker="Operación"
    :title="request()->routeIs('tires.stock') ? 'Stock' : 'Neumáticos'"
    :subtitle="request()->routeIs('tires.stock') ? 'Cubiertas disponibles para instalar.' : 'Buscá por modelo, número individual o DOT.'"
>
    <x-slot:actions>
        <x-export-csv :href="route('exports.tires', array_merge(request()->query(), request()->routeIs('tires.stock') ? ['stock_only' => 1, 'status' => 'STOCK'] : []))" />
        @if(auth()->user()->role->canWrite())
            <a href="{{ route('purchases.create') }}" class="btn btn-primary"><x-icon name="plus" class="w-4 h-4" /> Nueva compra</a>
        @endif
    </x-slot:actions>
</x-page-header>

<x-panel :flush="true">
    <x-slot:toolbar>
        <form class="toolbar" method="GET" data-catalog-row>
            <script type="application/json" id="tireCatalog">@json($catalog)</script>
            <input name="q" value="{{ request('q') }}" placeholder="FH:01, 30363 o DOT" aria-label="Buscar cubierta">
            <select name="brand_id" data-catalog="brand" data-empty="Todas las marcas" aria-label="Marca">
                <option value="">Todas las marcas</option>
                @foreach($catalog['brands'] as $brand)
                    <option value="{{ $brand['id'] }}" @selected((string) request('brand_id') === (string) $brand['id'])>{{ $brand['name'] }}</option>
                @endforeach
            </select>
            <select name="model_id" data-catalog="model" data-empty="Todos los modelos" data-selected="{{ request('model_id') }}" aria-label="Modelo">
                <option value="">Todos los modelos</option>
            </select>
            <select name="size_id" data-catalog="size" data-empty="Todas las medidas" data-selected="{{ request('size_id') }}" aria-label="Medida">
                <option value="">Todas las medidas</option>
            </select>
            @unless(request()->routeIs('tires.stock'))
                <select name="status" aria-label="Estado">
                    <option value="">Estado</option>
                    @foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status')==$status->value)>{{ $status->label() }}</option>@endforeach
                </select>
                    @endunless
                    <select name="condition" aria-label="Condición">
                        <option value="">Condición</option>
                        @foreach($conditions as $condition)<option value="{{ $condition->value }}" @selected(request('condition')==$condition->value)>{{ $condition->label() }}</option>@endforeach
                    </select>
                    @if(request('queue'))
                        <input type="hidden" name="queue" value="{{ request('queue') }}">
                    @endif
                    <button class="btn btn-dark btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Cubierta</th>
                <th scope="col">Medida</th>
                <th scope="col">Estado</th>
                <th scope="col">Condición</th>
                <th scope="col">Ubicación</th>
                <th scope="col" class="text-right">Km</th>
            </tr>
        </thead>
        <tbody>
        @forelse($tires as $tire)
            <tr>
                <td>
                    <a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a>
                    <div class="text-xs text-slate-500">{{ $tire->brand->name }}@if($tire->dot) · DOT {{ $tire->dot }}@endif</div>
                </td>
                <td>{{ $tire->size->displayName() }}</td>
                <td><x-status :tone="$tire->status->tone()">{{ $tire->status->label() }}</x-status></td>
                <td><x-status :tone="$tire->condition->tone()">{{ $tire->condition->label() }}</x-status></td>
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
            <tr><td colspan="6"><x-empty
                title="No hay cubiertas con ese filtro"
                :action="auth()->user()->role->canWrite() ? 'Nueva compra' : null"
                :href="route('purchases.create')"
            /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $tires->links() }}</div>
</x-panel>
@endsection
