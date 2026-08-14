@extends('layouts.app')
@section('kicker', 'Ayuda')
@section('title', 'Qué hace cada parte')
@section('content')
<x-page-header
    kicker="Ayuda"
    title="Qué hace cada parte, según tu rol"
    subtitle="Estás como {{ $profile['role']->label() }}. Abajo ves qué podés hacer vos y, más abajo, la tabla completa de todos los roles."
>
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('help.manual') }}">Manual de uso</a>
    </x-slot:actions>
</x-page-header>

<section class="help-you" aria-labelledby="helpYouTitle">
    <p class="help-you__kicker">Tu rol</p>
    <h2 id="helpYouTitle">{{ $profile['role']->label() }}</h2>
    <p class="help-you__summary">{{ $profile['summary'] }}</p>
    <p class="help-you__day">{{ $profile['day'] }}</p>
    <div class="help-you__cols">
        <div>
            <h3>Podés</h3>
            <ul>
                @foreach($profile['can'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        <div>
            <h3>No podés</h3>
            <ul>
                @foreach($profile['cannot'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

<h2 class="help-h">Cada sección del menú</h2>
<p class="help-lead">El recuadro marcado es lo que aplica a <strong>{{ $profile['role']->label() }}</strong>. Si dice No, esa acción no está habilitada para tu usuario.</p>

<div class="help-mods">
    @foreach($modules as $module)
        <article @class(['help-mod', 'is-off' => ! $module['you']])>
            <p class="help-mod__g">{{ $module['group'] }}</p>
            <h3>{{ $module['name'] }}</h3>
            <p>{{ $module['what'] }}</p>
            <div class="help-mod__foot">
                <span class="perm perm--{{ $module['perm'] }}">{{ \App\Support\SystemGuide::permLabel($module['perm']) }}</span>
                @if($module['you'] && $module['route'])
                    <a href="{{ route($module['route']) }}">Ir</a>
                @endif
            </div>
        </article>
    @endforeach
</div>

<h2 class="help-h" id="matriz">Matriz de permisos</h2>
<p class="help-lead">Lectura de izquierda a derecha: qué puede hacer cada rol en cada parte. Tu columna está resaltada.</p>

<div class="help-matrix-wrap">
    <table class="help-matrix">
        <thead>
            <tr>
                <th scope="col">Parte del sistema</th>
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

<h2 class="help-h">Los cinco roles</h2>
<div class="help-roles">
    @foreach($allRoles as $item)
        <article @class(['help-role', 'is-you' => $item['role'] === $profile['role']])>
            <h3>{{ $item['role']->label() }}@if($item['role'] === $profile['role']) <span>Tu usuario</span>@endif</h3>
            <p>{{ $item['summary'] }}</p>
            <p class="help-role__day">{{ $item['day'] }}</p>
        </article>
    @endforeach
</div>
@endsection
