@extends('layouts.app')
@section('kicker', 'Ayuda')
@section('title', 'Guía por rol')
@section('content')
<x-page-header
    kicker="Ayuda"
    title="Guía de permisos según el rol"
    subtitle="Su rol actual es {{ $profile['role']->label() }}. A continuación se detallan las acciones habilitadas y, más abajo, la matriz completa de todos los roles."
>
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('help.manual') }}">Manual de uso</a>
        <a class="btn btn-ghost" href="{{ route('help.starting-point') }}">Punto de partida</a>
    </x-slot:actions>
</x-page-header>

<section class="help-you" aria-labelledby="helpYouTitle">
    <p class="help-you__kicker">Rol asignado</p>
    <h2 id="helpYouTitle">{{ $profile['role']->label() }}</h2>
    <p class="help-you__summary">{{ $profile['summary'] }}</p>
    <p class="help-you__day">{{ $profile['day'] }}</p>
    <div class="help-you__cols">
        <div class="help-perm help-perm--can">
            <h3>
                <span class="help-perm__ico" aria-hidden="true"><x-icon name="shield" class="w-4 h-4" /></span>
                Permisos habilitados
            </h3>
            <ul>
                @foreach($profile['can'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        <div class="help-perm help-perm--cannot">
            <h3>
                <span class="help-perm__ico" aria-hidden="true"><x-icon name="alert" class="w-4 h-4" /></span>
                Limitaciones del rol
            </h3>
            <ul>
                @foreach($profile['cannot'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

<h2 class="help-h">Secciones del menú</h2>
<p class="help-lead">El recuadro resaltado corresponde a <strong>{{ $profile['role']->label() }}</strong>. Si indica No, esa acción no está habilitada para su usuario.</p>

<div class="help-mods">
    @foreach($modules as $module)
        <article @class(['help-mod', 'is-off' => ! $module['you']])>
            <p class="help-mod__g">{{ $module['group'] }}</p>
            <h3>{{ $module['name'] }}</h3>
            <p>{{ $module['what'] }}</p>
            <div class="help-mod__foot">
                <span class="perm perm--{{ $module['perm'] }}">{{ \App\Support\SystemGuide::permLabel($module['perm']) }}</span>
                @if($module['you'] && $module['route'])
                    <a href="{{ route($module['route']) }}">Abrir</a>
                @endif
            </div>
        </article>
    @endforeach
</div>

<h2 class="help-h" id="matriz">Matriz de permisos</h2>
<p class="help-lead">Lectura de izquierda a derecha: qué puede realizar cada rol en cada módulo. Su columna aparece resaltada.</p>

<div class="help-matrix-wrap">
    <table class="help-matrix">
        <thead>
            <tr>
                <th scope="col">Módulo</th>
                @foreach($matrixRoles as $colRole)
                    <th scope="col" @class(['is-you' => $colRole === $profile['role']])>{{ $colRole->label() }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($matrixModules as $module)
                <tr>
                    <th scope="row">
                        <span>{{ $module['name'] }}</span>
                        <small>{{ $module['what'] }}</small>
                    </th>
                    @foreach($matrixRoles as $colRole)
                        @php $code = $module['cells'][$colRole->value]; @endphp
                        <td @class(['is-you' => $colRole === $profile['role']])>
                            <span class="perm perm--{{ $code }}">{{ \App\Support\SystemGuide::permLabel($code) }}</span>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<h2 class="help-h">Roles del sistema</h2>
<div class="help-roles">
    @foreach($allRoles as $item)
        <article @class(['help-role', 'is-you' => $item['role'] === $profile['role']])>
            <h3>{{ $item['role']->label() }}@if($item['role'] === $profile['role']) <span>Su usuario</span>@endif</h3>
            <p>{{ $item['summary'] }}</p>
            <p class="help-role__day">{{ $item['day'] }}</p>
        </article>
    @endforeach
</div>
@endsection
