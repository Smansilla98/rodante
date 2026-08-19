@extends('layouts.app')
@section('title', 'Nueva orden de trabajo')
@section('content')
<x-page-header kicker="Taller" title="Nueva orden" subtitle="La cubierta tiene que estar en stock. No se monta directo desde el taller.">
    <x-slot:actions>
        <a href="{{ route('work-orders.index') }}" class="btn btn-ghost">Volver</a>
    </x-slot:actions>
</x-page-header>
<form method="POST" action="{{ route('work-orders.store') }}" class="panel max-w-xl">
    @csrf
    <div class="panel__body space-y-4">
        <label class="field"><span>Cubierta</span>
            <select name="tire_id" class="inp" required>
                @foreach($tires as $tire)
                    <option value="{{ $tire->id }}">{{ $tire->displayName() }} · {{ $tire->status->label() }}</option>
                @endforeach
            </select>
            <x-field-error name="tire_id" />
        </label>
        <label class="field"><span>Recapadora</span>
            <select name="retread_shop_id" class="inp" required>
                @foreach($shops as $shop)
                    <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                @endforeach
            </select>
            <x-field-error name="retread_shop_id" />
        </label>
        <label class="field"><span>Tipo</span>
            <select name="type" class="inp" required>
                @foreach($types as $type)
                    @if($type->value !== 'RECAPADO' || auth()->user()->role->canRetireOrRecap())
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endif
                @endforeach
            </select>
        </label>
        <label class="field"><span>Notas</span><textarea name="notes" rows="2" class="inp"></textarea></label>
        <button class="btn btn-primary">Abrir orden</button>
    </div>
</form>
@endsection
