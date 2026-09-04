@extends('layouts.app')
@section('title', 'Nueva orden de trabajo')
@section('content')
<x-page-header kicker="Taller" title="Nueva orden" subtitle="Recapado admite varias cubiertas. Reparación es de una sola. Tienen que estar en stock.">
    <x-slot:actions>
        <a href="{{ route('work-orders.index') }}" class="btn btn-ghost">Volver</a>
    </x-slot:actions>
</x-page-header>
<form method="POST" action="{{ route('work-orders.store') }}" class="panel max-w-xl" id="workOrderForm">
    @csrf
    <div class="panel__body space-y-4">
        <label class="field"><span>Tipo</span>
            <select name="type" id="woType" class="inp" required>
                @foreach($types as $type)
                    @if($type->value !== 'RECAPADO' || auth()->user()->role->canRetireOrRecap())
                        <option value="{{ $type->value }}" @selected(old('type', 'RECAPADO') === $type->value)>{{ $type->label() }}</option>
                    @endif
                @endforeach
            </select>
        </label>

        <div id="woSingleWrap">
            <label class="field"><span>Cubierta</span>
                <select name="tire_id" id="woSingleTire" class="inp">
                    <option value="">Elegí una cubierta</option>
                    @foreach($tires as $tire)
                        <option value="{{ $tire->id }}" @selected((string) old('tire_id') === (string) $tire->id)>{{ $tire->displayName() }} · {{ $tire->status->label() }}</option>
                    @endforeach
                </select>
                <x-field-error name="tire_id" />
            </label>
        </div>

        <div id="woMultiWrap" hidden>
            <p class="field"><span>Cubiertas del lote</span></p>
            <input type="search" id="woTireSearch" class="inp" placeholder="Buscar Nº o modelo" autocomplete="off">
            <p class="hint" id="woMultiHint">Marcá las cubiertas que van juntas a recapado.</p>
            <div class="wo-pick" id="woTirePick">
                @php $oldIds = collect(old('tire_ids', []))->map(fn ($id) => (string) $id); @endphp
                @foreach($tires as $tire)
                    <label class="wo-pick__row" data-label="{{ strtolower($tire->displayName().' '.$tire->status->label()) }}">
                        <input type="checkbox" name="tire_ids[]" value="{{ $tire->id }}" @checked($oldIds->contains((string) $tire->id))>
                        <span>
                            <strong>{{ $tire->displayName() }}</strong>
                            <em>{{ $tire->status->label() }}</em>
                        </span>
                    </label>
                @endforeach
            </div>
            <x-field-error name="tire_ids" />
        </div>

        <label class="field"><span>Recapadora</span>
            <select name="retread_shop_id" class="inp" required>
                @foreach($shops as $shop)
                    <option value="{{ $shop->id }}" @selected((string) old('retread_shop_id') === (string) $shop->id)>{{ $shop->name }}</option>
                @endforeach
            </select>
            <x-field-error name="retread_shop_id" />
        </label>
        <label class="field"><span>Notas</span><textarea name="notes" rows="2" class="inp">{{ old('notes') }}</textarea></label>
        <x-field-error name="error" />
        <button class="btn btn-primary">Abrir orden</button>
    </div>
</form>
@endsection
