@extends('layouts.app')
@section('title', 'Inventario')
@section('content')
<x-page-header kicker="Consulta" title="Inventario teórico" subtitle="Listado del sistema. Para conteo con diferencias usá Inventario físico.">
    <x-slot:actions>
        @if(auth()->user()->role->canWrite())
            <a href="{{ route('inventories.index') }}" class="btn btn-primary">Inventario físico</a>
        @endif
    </x-slot:actions>
</x-page-header>
<x-panel :flush="true">
    <x-content-table>
        <thead>
            <tr>
                <th>Nº</th>
                <th>Cubierta</th>
                <th>Estado</th>
                <th>Ubicación esperada</th>
            </tr>
        </thead>
        <tbody>
        @forelse($tires as $tire)
            <tr>
                <td class="mono">{{ $tire->individual_number }}</td>
                <td><a href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a></td>
                <td>{{ $tire->status->label() }}</td>
                <td>
                    @if($tire->currentLocation?->unit)
                        {{ $tire->currentLocation->unit->plate }}
                    @else
                        {{ $tire->currentLocation?->base?->name ?? $tire->status->label() }}
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4"><x-empty title="Sin cubiertas" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $tires->links() }}</div>
</x-panel>
@endsection
