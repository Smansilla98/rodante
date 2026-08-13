@extends('layouts.app')
@section('kicker', 'Operación')
@section('title', 'Nueva compra')
@section('content')
<x-page-header kicker="Operación" title="Nueva compra" subtitle="En pantalla se verán como FH:01 Nº30363.">
    <x-slot:actions>
        <a href="{{ route('purchases.index') }}" class="btn btn-ghost"><x-icon name="back" class="w-4 h-4" /> Volver</a>
    </x-slot:actions>
</x-page-header>

<form method="POST" action="{{ route('purchases.store') }}">
    @csrf
    <x-panel title="Datos de la compra">
        <div class="grid md:grid-cols-3 gap-4 mb-6">
            <label class="field"><span>Proveedor</span>
                <select name="supplier_id" required>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select>
            </label>
            <label class="field"><span>Base</span>
                <select name="base_id" required>@foreach($bases as $base)<option value="{{ $base->id }}">{{ $base->name }}</option>@endforeach</select>
            </label>
            <label class="field"><span>Fecha</span>
                <input type="date" name="purchased_at" required value="{{ now()->toDateString() }}">
            </label>
        </div>
        <h3 class="font-semibold mb-3">Líneas</h3>
        <div class="space-y-3 mb-4">
            @for($i=0;$i<3;$i++)
                <div class="grid md:grid-cols-5 gap-2">
                    <select name="items[{{ $i }}][tire_brand_id]" class="inp">
                        <option value="">Marca</option>
                        @foreach($brands as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach
                    </select>
                    <select name="items[{{ $i }}][tire_model_id]" class="inp">
                        <option value="">Modelo</option>
                        @foreach($models as $model)<option value="{{ $model->id }}">{{ $model->code }}</option>@endforeach
                    </select>
                    <select name="items[{{ $i }}][tire_size_id]" class="inp">
                        <option value="">Medida</option>
                        @foreach($sizes as $size)<option value="{{ $size->id }}">{{ $size->displayName() }}</option>@endforeach
                    </select>
                    <input name="items[{{ $i }}][quantity]" type="number" min="1" class="inp" placeholder="Cantidad">
                    <input name="items[{{ $i }}][first_number]" type="number" min="1" class="inp" placeholder="Desde Nº">
                </div>
            @endfor
        </div>
        <label class="field mb-4"><span>Notas</span><textarea name="notes" rows="2"></textarea></label>
        <button class="btn btn-primary">Crear borrador</button>
    </x-panel>
</form>
@endsection
