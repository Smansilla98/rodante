@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Tipos y motivos')
@section('content')
@php
    $editingType = $types->firstWhere('id', (int) request('edit_type'));
    $editingReason = $reasons->firstWhere('id', (int) request('edit_reason'));
    $editingConfig = $configurations->firstWhere('id', (int) request('edit_config'));
@endphp
<x-page-header kicker="Catálogo" title="Tipos y configuraciones" subtitle="El tipo de unidad va separado de la fórmula de ejes. Las posiciones de cada cubierta salen de la configuración." />
<div class="grid md:grid-cols-2 gap-5 mb-5">
    <div>
        <x-panel :title="$editingType ? 'Modificar tipo' : 'Nuevo tipo'">
            <form method="POST" action="{{ $editingType ? route('types.update', $editingType) : route('types.store') }}" class="space-y-3">
                @csrf
                @if($editingType) @method('PUT') @endif
                <label class="field"><span>Código</span><input name="code" value="{{ old('code', $editingType?->code) }}"></label>
                <label class="field"><span>Nombre</span><input name="name" value="{{ old('name', $editingType?->name) }}"></label>
                <label class="flex gap-2 items-center text-sm"><input type="checkbox" name="has_odometer" value="1" @checked(old('has_odometer', $editingType?->has_odometer))> Tiene odómetro</label>
                @if($editingType)
                    <label class="flex gap-2 items-center text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingType->is_active))> Activo</label>
                @endif
                <x-abm-actions :editing="(bool) $editingType" :cancel="route('types.index')" addLabel="Agregar tipo" />
            </form>
            @if($editingType)
                <div class="abm-actions mt-3">
                    <x-abm-delete :action="route('types.destroy', $editingType)" confirm="¿Eliminar este tipo?" />
                </div>
            @endif
        </x-panel>
        <x-panel title="Tipos" :flush="true" class="mt-5">
            <x-content-table>
                <thead>
                    <tr>
                        <th scope="col">Tipo</th>
                        <th scope="col">Odómetro</th>
                        <th scope="col" class="text-right"> </th>
                    </tr>
                </thead>
                <tbody>
                @forelse($types as $type)
                    <tr>
                        <td>{{ $type->name }}</td>
                        <td>{{ $type->has_odometer ? 'Propio' : 'Usa tractor' }}</td>
                        <td class="text-right"><a class="abm-link" href="{{ route('types.index', ['edit_type' => $type->id]) }}">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="3"><x-empty title="Sin tipos" /></td></tr>
                @endforelse
                </tbody>
            </x-content-table>
        </x-panel>
    </div>
    <div>
        <x-panel :title="$editingReason ? 'Modificar motivo' : 'Nuevo motivo'">
            <form method="POST" action="{{ $editingReason ? route('reasons.update', $editingReason) : route('reasons.store') }}" class="space-y-3">
                @csrf
                @if($editingReason) @method('PUT') @endif
                <label class="field"><span>Código</span><input name="code" value="{{ old('code', $editingReason?->code) }}"></label>
                <label class="field"><span>Nombre</span><input name="name" value="{{ old('name', $editingReason?->name) }}"></label>
                <label class="field"><span>Aplica a</span>
                    <select name="applies_to">
                        @foreach(['RETIRO', 'BAJA', 'OTRO'] as $applies)
                            <option value="{{ $applies }}" @selected(old('applies_to', $editingReason?->applies_to) === $applies)>{{ $applies }}</option>
                        @endforeach
                    </select>
                </label>
                @if($editingReason)
                    <label class="flex gap-2 items-center text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingReason->is_active))> Activo</label>
                @endif
                <x-abm-actions :editing="(bool) $editingReason" :cancel="route('types.index')" addLabel="Agregar motivo" />
            </form>
            @if($editingReason)
                <div class="abm-actions mt-3">
                    <x-abm-delete :action="route('reasons.destroy', $editingReason)" confirm="¿Eliminar este motivo?" />
                </div>
            @endif
        </x-panel>
        <x-panel title="Motivos" :flush="true" class="mt-5">
            <x-content-table>
                <thead>
                    <tr>
                        <th scope="col">Motivo</th>
                        <th scope="col">Aplica a</th>
                        <th scope="col" class="text-right"> </th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reasons as $reason)
                    <tr>
                        <td>{{ $reason->name }}</td>
                        <td class="mono">{{ $reason->applies_to }}</td>
                        <td class="text-right"><a class="abm-link" href="{{ route('types.index', ['edit_reason' => $reason->id]) }}">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="3"><x-empty title="Sin motivos" /></td></tr>
                @endforelse
                </tbody>
            </x-content-table>
        </x-panel>
    </div>
</div>

<x-panel :title="$editingConfig ? 'Modificar configuración' : 'Configuraciones de ejes'" :flush="! $editingConfig">
    @if($editingConfig)
        <form method="POST" action="{{ route('configurations.update', $editingConfig) }}" class="toolbar mb-4">
            @csrf
            @method('PUT')
            <span class="mono">{{ $editingConfig->code }}</span>
            <input name="name" value="{{ old('name', $editingConfig->name) }}" required>
            <input name="description" value="{{ old('description', $editingConfig->description) }}" placeholder="Descripción">
            <label class="abm-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingConfig->is_active))> Activa</label>
            <x-abm-actions :editing="true" :cancel="route('types.index')" />
        </form>
        <div class="abm-actions mb-4">
            <x-abm-delete :action="route('configurations.destroy', $editingConfig)" confirm="¿Eliminar esta configuración?" />
        </div>
    @endif
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Código</th>
                <th scope="col">Nombre</th>
                <th scope="col">Aplica a</th>
                <th scope="col">Ejes</th>
                <th scope="col">Cubiertas</th>
                <th scope="col">Descripción</th>
                <th scope="col" class="text-right"> </th>
            </tr>
        </thead>
        <tbody>
        @forelse($configurations as $cfg)
            <tr>
                <td class="mono">{{ $cfg->code }}</td>
                <td>{{ $cfg->name }}</td>
                <td>{{ implode(', ', $cfg->compatible_types ?? []) }}</td>
                <td class="mono">{{ $cfg->axle_count }}</td>
                <td class="mono">{{ $cfg->positions->where('is_spare', false)->count() }}</td>
                <td class="text-slate-500">{{ $cfg->description }}</td>
                <td class="text-right"><a class="abm-link" href="{{ route('types.index', ['edit_config' => $cfg->id]) }}">Editar</a></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty title="Sin configuraciones" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
</x-panel>
@endsection
