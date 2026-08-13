@extends('layouts.app')
@section('kicker', 'Catálogo')
@section('title', 'Usuarios')
@section('content')
<x-page-header kicker="Catálogo" title="Usuarios" subtitle="Acceso por usuario, no por email." />
<x-panel title="Nuevo usuario">
    <form method="POST" action="{{ route('users.store') }}" class="grid md:grid-cols-3 gap-3">
        @csrf
        <label class="field"><span>Nombre</span><input name="name" required></label>
        <label class="field"><span>Usuario</span><input name="username" required></label>
        <label class="field"><span>Contraseña</span><input name="password" type="password" required></label>
        <label class="field"><span>Rol</span>
            <select name="role">@foreach($roles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</select>
        </label>
        <div class="flex items-end"><button class="btn btn-primary">Crear</button></div>
    </form>
</x-panel>
<x-panel title="Listado" :flush="true" class="mt-5">
    @forelse($users as $user)
        <div class="list-row px-5">
            <span>
                <strong>{{ $user->name }}</strong>
                <span class="text-slate-500">· {{ $user->username }}</span>
            </span>
            <x-status tone="slate">{{ $user->role->label() }}</x-status>
        </div>
    @empty
        <x-empty title="No hay usuarios" />
    @endforelse
</x-panel>
@endsection
