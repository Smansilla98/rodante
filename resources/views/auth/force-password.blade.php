@extends('layouts.app')
@section('title', 'Cambiar contraseña')
@section('content')
<x-page-header title="Cambiar contraseña" subtitle="Tu usuario requiere una contraseña nueva antes de continuar." />
<x-panel title="Nueva contraseña">
    <form method="POST" action="{{ route('password.force.update') }}" class="grid gap-3 max-w-md">
        @csrf
        <label class="field"><span>Contraseña</span><input type="password" name="password" required autocomplete="new-password"><x-field-error name="password" /></label>
        <label class="field"><span>Repetir</span><input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        <button class="btn btn-primary" type="submit">Guardar</button>
    </form>
</x-panel>
@endsection
