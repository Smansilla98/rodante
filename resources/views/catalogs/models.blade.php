@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Modelos')
@section('content')
@php $editing = $models->firstWhere('id', (int) request('edit')); @endphp
<x-page-header kicker="Catálogo" title="Modelos" subtitle="El código es lo que se ve en pantalla: FH:01 Nº30363. La aplicación define dónde puede montarse." />
<x-panel :title="$editing ? 'Modificar modelo' : 'Nuevo modelo'">
    <form method="POST" action="{{ $editing ? route('models.update', $editing) : route('models.store') }}" class="space-y-3">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="grid md:grid-cols-4 gap-2">
            <label class="field"><span>Marca</span>
            <select name="tire_brand_id" class="inp">
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected((string) old('tire_brand_id', $editing?->tire_brand_id) === (string) $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
            </label>
            <label class="field"><span>Código</span>
                <input name="code" class="inp" value="{{ old('code', $editing?->code) }}" required>
                <x-field-error name="code" />
            </label>
            <label class="field"><span>Nombre</span>
                <input name="name" class="inp" value="{{ old('name', $editing?->name) }}">
            </label>
            <label class="field"><span>Aplicación</span>
            <select name="application" class="inp" required>
                @foreach($applications as $application)
                    <option value="{{ $application->value }}" @selected(old('application', $editing?->application?->value) === $application->value)>{{ $application->label() }}</option>
                @endforeach
            </select>
            </label>
        </div>
        <div class="abm-sizes">
            @foreach($sizes as $size)
                <label class="abm-check">
                    <input type="checkbox" name="size_ids[]" value="{{ $size->id }}" @checked(in_array($size->id, old('size_ids', $editing?->sizes?->pluck('id')->all() ?? []), false))>
                    {{ $size->code }}
                </label>
            @endforeach
        </div>
        @if($editing)
            <label class="abm-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active))> Activo</label>
        @endif
        <x-abm-actions :editing="(bool) $editing" :cancel="route('models.index')" />
    </form>
    @if($editing)
        <div class="abm-actions mt-3">
            <x-abm-delete :action="route('models.destroy', $editing)" confirm="¿Eliminar este modelo?" />
        </div>
    @endif
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Marca</th>
                <th scope="col">Código</th>
                <th scope="col">Nombre</th>
                <th scope="col">Aplicación</th>
                <th scope="col">Medidas</th>
                <th scope="col" class="text-right"> </th>
            </tr>
        </thead>
        <tbody>
        @forelse($models as $model)
            <tr>
                <td>{{ $model->brand->name }}</td>
                <td class="mono">{{ $model->code }}</td>
                <td>{{ $model->name }}</td>
                <td>{{ $model->application?->label() ?? 'Mixta' }}</td>
                <td>{{ $model->sizes->pluck('code')->join(', ') ?: '—' }}</td>
                <td class="text-right"><a class="abm-link" href="{{ route('models.index', ['edit' => $model->id]) }}">Editar</a></td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty title="No hay modelos" action="Nuevo modelo" :href="route('models.index')" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
</x-panel>
@endsection
