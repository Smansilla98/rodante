@extends('layouts.app')
@section('kicker', 'Consulta')
@section('title', 'Enganches')
@section('content')
<x-page-header kicker="Consulta" title="Enganches" subtitle="Acoples tractor–semi/tanque/batea. Los abiertos siguen sumando km al acoplado.">
    <x-slot:actions>
        <x-export-csv :href="route('exports.couplings', request()->query())" />
    </x-slot:actions>
</x-page-header>

<x-panel :flush="true">
    <x-slot:toolbar>
        <form class="toolbar" method="GET">
            <select name="tractor_id" aria-label="Tractor">
                <option value="">Tractor</option>
                @foreach($tractors as $unit)
                    <option value="{{ $unit->id }}" @selected((string) request('tractor_id') === (string) $unit->id)>{{ $unit->plate }}</option>
                @endforeach
            </select>
            <select name="trailer_id" aria-label="Acoplado">
                <option value="">Acoplado</option>
                @foreach($trailers as $unit)
                    <option value="{{ $unit->id }}" @selected((string) request('trailer_id') === (string) $unit->id)>{{ $unit->plate }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Estado">
                <option value="">Todos</option>
                <option value="open" @selected(request('status') === 'open')>Abiertos</option>
                <option value="closed" @selected(request('status') === 'closed')>Cerrados</option>
            </select>
            <input type="date" name="from" value="{{ request('from') }}" aria-label="Desde">
            <input type="date" name="to" value="{{ request('to') }}" aria-label="Hasta">
            <button class="btn btn-dark btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>
    <x-content-table class="table-enganches">
        <thead>
            <tr>
                <th scope="col" class="col-estado">Estado</th>
                <th scope="col" class="col-plate">Tractor</th>
                <th scope="col" class="col-plate">Acoplado</th>
                <th scope="col" class="col-fecha">Desde</th>
                <th scope="col" class="col-fecha">Hasta</th>
                <th scope="col" class="col-odo text-right">Odómetro</th>
            </tr>
        </thead>
        <tbody>
        @forelse($couplings as $coupling)
            <tr>
                <td class="col-estado">
                    @if($coupling->isOpen())
                        <x-status tone="ok">Abierto</x-status>
                    @else
                        <x-status tone="slate">Cerrado</x-status>
                    @endif
                </td>
                <td class="col-plate"><a href="{{ route('units.show', $coupling->tractor) }}">{{ $coupling->tractor?->plate }}</a></td>
                <td class="col-plate"><a href="{{ route('units.show', $coupling->trailer) }}">{{ $coupling->trailer?->plate }}</a></td>
                <td class="col-fecha mono">{{ $coupling->coupled_at?->format('d/m/Y H:i') }}</td>
                <td class="col-fecha mono">{{ $coupling->uncoupled_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="col-odo mono text-right">
                    {{ number_format($coupling->tractor_odometer_start) }}
                    @if($coupling->tractor_odometer_end !== null)
                        → {{ number_format($coupling->tractor_odometer_end) }}
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty title="Sin enganches" text="Cuando se acople un semi o tanque, aparece acá." /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    <div class="pager">{{ $couplings->links() }}</div>
</x-panel>
@endsection
