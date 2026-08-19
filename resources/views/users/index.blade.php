@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Usuarios')
@section('content')
@php $editing = $users->firstWhere('id', (int) request('edit')); @endphp
<x-page-header kicker="Catálogo" title="Usuarios" subtitle="Acceso por usuario, no por email." />
<x-panel :title="$editing ? 'Modificar usuario' : 'Nuevo usuario'">
    <form method="POST" action="{{ $editing ? route('users.update', $editing) : route('users.store') }}" class="grid md:grid-cols-3 gap-3">
        @csrf
        @if($editing) @method('PUT') @endif
        <label class="field"><span>Nombre</span><input name="name" value="{{ old('name', $editing?->name) }}" required @error('name') aria-invalid="true" @enderror><x-field-error name="name" /></label>
        <label class="field"><span>Usuario</span><input name="username" value="{{ old('username', $editing?->username) }}" required @error('username') aria-invalid="true" @enderror><x-field-error name="username" /></label>
        <label class="field"><span>Contraseña</span><input name="password" type="password" {{ $editing ? '' : 'required' }} placeholder="{{ $editing ? 'Dejar vacía para no cambiar' : 'Mínimo 8 caracteres' }}" @error('password') aria-invalid="true" @enderror><x-field-error name="password" /></label>
        <label class="field"><span>Email</span><input name="email" type="email" value="{{ old('email', $editing?->email) }}"></label>
        <label class="field"><span>Rol</span>
            <select name="role">
                @foreach($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $editing?->role?->value) === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </label>
        @if($editing)
            <label class="flex gap-2 items-center text-sm self-end"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active))> Activo</label>
        @endif
        <div class="md:col-span-3 abm-sizes">
            @foreach($fleets as $fleet)
                <label class="abm-check"><input type="checkbox" name="fleet_ids[]" value="{{ $fleet->id }}" @checked(in_array($fleet->id, old('fleet_ids', $editing?->fleets?->pluck('id')->all() ?? []), false))> {{ $fleet->name }}</label>
            @endforeach
        </div>
        <div class="md:col-span-3 abm-sizes">
            @foreach($bases as $base)
                <label class="abm-check"><input type="checkbox" name="base_ids[]" value="{{ $base->id }}" @checked(in_array($base->id, old('base_ids', $editing?->bases?->pluck('id')->all() ?? []), false))> {{ $base->name }}</label>
            @endforeach
        </div>
        <div class="md:col-span-3">
            <x-abm-actions :editing="(bool) $editing" :cancel="route('users.index')" addLabel="Crear" />
        </div>
    </form>
    @if($editing && $editing->id !== auth()->id())
        <div class="abm-actions mt-3">
            <x-abm-delete :action="route('users.destroy', $editing)" confirm="¿Eliminar este usuario?" />
        </div>
    @endif
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Nombre</th>
                <th scope="col">Usuario</th>
                <th scope="col">Rol</th>
                <th scope="col" class="text-right"> </th>
            </tr>
        </thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td class="mono">{{ $user->username }}</td>
                <td><x-status tone="slate">{{ $user->role->label() }}</x-status></td>
                <td class="text-right"><a class="abm-link" href="{{ route('users.index', ['edit' => $user->id]) }}">Editar</a></td>
            </tr>
        @empty
            <tr><td colspan="4"><x-empty title="No hay usuarios" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
</x-panel>
@endsection
