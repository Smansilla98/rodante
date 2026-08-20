@extends('layouts.app')
@section('title', 'Costo por unidad / posición')
@section('content')
<x-page-header
    kicker="Consulta"
    title="Costo por unidad y posición"
    subtitle="Suma de asientos con atribución. El $/km por cubierta no cambia: sigue siendo costo con tire_id / km."
/>

<div class="grid lg:grid-cols-2 gap-5">
    <x-panel title="Por unidad" :flush="true">
        <x-content-table>
            <thead>
                <tr>
                    <th>Unidad</th>
                    <th>Asientos</th>
                    <th>Cubiertas</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
            @forelse($byUnit as $row)
                <tr>
                    <td>
                        <a href="{{ route('units.show', $row->fleet_unit_id) }}">{{ $row->plate }}</a>
                    </td>
                    <td class="mono">{{ $row->entries_count }}</td>
                    <td class="mono">{{ $row->tire_count }}</td>
                    <td class="mono">$ {{ number_format($row->total_amount, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty title="Sin costos atribuidos a unidad" text="Aparecen al cerrar OT con historial de montaje o al registrar con contexto." /></td></tr>
            @endforelse
            </tbody>
        </x-content-table>
    </x-panel>

    <x-panel title="Por posición" :flush="true">
        <x-content-table>
            <thead>
                <tr>
                    <th>Posición</th>
                    <th>Asientos</th>
                    <th>Cubiertas</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
            @forelse($byPosition as $row)
                <tr>
                    <td>
                        {{ $row->position_name }}
                        @if($row->position_code)
                            <span class="hint mono">{{ $row->position_code }}</span>
                        @endif
                    </td>
                    <td class="mono">{{ $row->entries_count }}</td>
                    <td class="mono">{{ $row->tire_count }}</td>
                    <td class="mono">$ {{ number_format($row->total_amount, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty title="Sin costos atribuidos a posición" /></td></tr>
            @endforelse
            </tbody>
        </x-content-table>
    </x-panel>
</div>
@endsection
