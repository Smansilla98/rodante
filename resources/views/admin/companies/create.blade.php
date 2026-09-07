@extends('layouts.app')
@section('kicker', 'Administración')
@section('title', 'Nueva empresa')
@section('content')
<x-page-header kicker="Administración" title="Nueva empresa" subtitle="Se copia el catálogo base y se crea un administrador inicial." />
<x-panel title="Datos">
    <form method="POST" action="{{ route('admin.companies.store') }}" class="grid md:grid-cols-2 gap-3">
        @csrf
        <label class="field"><span>Nombre</span><input name="name" value="{{ old('name') }}" required><x-field-error name="name" /></label>
        <label class="field"><span>Slug</span><input name="slug" value="{{ old('slug') }}" required placeholder="acme"><x-field-error name="slug" /></label>
        <label class="field"><span>CUIT / tax id</span><input name="tax_id" value="{{ old('tax_id') }}"><x-field-error name="tax_id" /></label>
        <div></div>
        <label class="field"><span>Admin — nombre</span><input name="admin_name" value="{{ old('admin_name') }}" required><x-field-error name="admin_name" /></label>
        <label class="field"><span>Admin — usuario</span><input name="admin_username" value="{{ old('admin_username', 'admin') }}" required><x-field-error name="admin_username" /></label>
        <label class="field md:col-span-2"><span>Admin — email</span><input name="admin_email" type="email" value="{{ old('admin_email') }}"><x-field-error name="admin_email" /></label>
        <div class="md:col-span-2">
            <button class="btn btn-primary" type="submit">Crear empresa</button>
            <a class="btn btn-ghost" href="{{ route('admin.companies.index') }}">Cancelar</a>
        </div>
    </form>
</x-panel>
@endsection
