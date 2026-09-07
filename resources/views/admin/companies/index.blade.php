@extends('layouts.app')
@section('kicker', 'Administración')
@section('title', 'Empresas')
@section('content')
<x-page-header kicker="Administración" title="Empresas" subtitle="Alta y activación. No se borran empresas.">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('admin.companies.create') }}">Nueva empresa</a>
    </x-slot:actions>
</x-page-header>

<x-panel title="Listado">
    <x-content-table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Slug</th>
                <th>Usuarios</th>
                <th>Unidades</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($companies as $row)
                @php $c = $row['model']; @endphp
                <tr>
                    <td>{{ $c->name }}</td>
                    <td>{{ $c->slug }}</td>
                    <td>{{ $row['users_count'] }}</td>
                    <td>{{ $row['units_count'] }}</td>
                    <td>{{ $c->is_active ? 'Activa' : 'Inactiva' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.companies.toggle', $c) }}">
                            @csrf
                            <button class="btn btn-ghost" type="submit">{{ $c->is_active ? 'Desactivar' : 'Activar' }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-content-table>
</x-panel>
@endsection
