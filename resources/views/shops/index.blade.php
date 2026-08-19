@extends('layouts.app')
@section('title', 'Recapadoras')
@section('content')
<x-page-header kicker="Taller" title="Recapadoras" subtitle="Talleres de recapado y reparación de la empresa." />
@if(auth()->user()->role->canWrite())
<x-panel :title="$editing ? 'Modificar recapadora' : 'Nueva recapadora'">
    <form method="POST" action="{{ $editing ? route('shops.update', $editing) : route('shops.store') }}" class="grid md:grid-cols-2 gap-3">
        @csrf
        @if($editing) @method('PUT') @endif
        <label class="field"><span>Nombre</span><input name="name" value="{{ old('name', $editing?->name) }}" required><x-field-error name="name" /></label>
        <label class="field"><span>CUIT</span><input name="tax_id" value="{{ old('tax_id', $editing?->tax_id) }}"></label>
        <label class="field"><span>Teléfono</span><input name="phone" value="{{ old('phone', $editing?->phone) }}"></label>
        <label class="field"><span>Dirección</span><input name="address" value="{{ old('address', $editing?->address) }}"></label>
        @if($editing)
            <label class="abm-check self-end"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active))> Activa</label>
        @endif
        <div class="md:col-span-2"><x-abm-actions :editing="(bool) $editing" :cancel="route('shops.index')" addLabel="Cargar" /></div>
    </form>
</x-panel>
@endif
<x-panel title="Listado" :flush="true" class="mt-5">
    <x-content-table>
        <thead><tr><th>Nombre</th><th>CUIT</th><th>Teléfono</th><th></th></tr></thead>
        <tbody>
        @forelse($shops as $shop)
            <tr>
                <td>{{ $shop->name }}</td>
                <td class="mono">{{ $shop->tax_id ?: '—' }}</td>
                <td>{{ $shop->phone ?: '—' }}</td>
                <td class="text-right">@if(auth()->user()->role->canWrite())<a class="abm-link" href="{{ route('shops.index', ['edit' => $shop->id]) }}">Editar</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="4"><x-empty title="No hay recapadoras" :action="auth()->user()->role->canWrite() ? 'Cargar recapadora' : null" :href="route('shops.index')" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
</x-panel>
@endsection
