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
        <p class="hint mb-3">Primero la marca, después el diseño de esa marca, después la medida en la que se fabrica.</p>
        <script type="application/json" id="tireCatalog">@json($catalog)</script>
        <div class="space-y-3 mb-4">
            @for($i=0;$i<3;$i++)
                <div class="grid md:grid-cols-6 gap-2" data-catalog-row>
                    <select name="items[{{ $i }}][tire_brand_id]" class="inp" data-catalog="brand" aria-label="Marca">
                        <option value="">Marca</option>
                        @foreach($catalog['brands'] as $brand)
                            <option value="{{ $brand['id'] }}">{{ $brand['name'] }}</option>
                        @endforeach
                    </select>
                    <select name="items[{{ $i }}][tire_model_id]" class="inp" data-catalog="model" aria-label="Modelo">
                        <option value="">Modelo</option>
                    </select>
                    <select name="items[{{ $i }}][tire_size_id]" class="inp" data-catalog="size" aria-label="Medida">
                        <option value="">Medida</option>
                    </select>
                    <input name="items[{{ $i }}][quantity]" type="number" min="1" class="inp" placeholder="Cantidad" aria-label="Cantidad">
                    <input name="items[{{ $i }}][first_number]" type="number" min="1" class="inp" placeholder="Desde Nº" aria-label="Número inicial">
                    <input name="items[{{ $i }}][unit_cost]" type="number" min="0" step="0.01" class="inp" placeholder="Costo" aria-label="Costo unitario">
                </div>
            @endfor
        </div>
        <label class="field mb-4"><span>Notas</span><textarea name="notes" rows="2"></textarea></label>
        <button class="btn btn-primary">Crear borrador</button>
    </x-panel>
</form>

@if(auth()->user()->role->canWrite())
<form method="POST" action="{{ route('purchases.import') }}" enctype="multipart/form-data" class="panel max-w-xl mt-5">
    @csrf
    <div class="panel__body space-y-3">
        <h3 class="font-semibold">Importar CSV</h3>
        <p class="hint">Columnas: Marca;Modelo;Medida;Cantidad;Desde Nº;Costo unitario. El catálogo tiene que existir. Las filas inválidas se rechazan.</p>
        <label class="field"><span>Archivo</span><input type="file" name="file" accept=".csv,text/csv" required><x-field-error name="file" /></label>
        <label class="field"><span>Proveedor</span>
            <select name="supplier_id" required>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select>
        </label>
        <label class="field"><span>Base</span>
            <select name="base_id" required>@foreach($bases as $base)<option value="{{ $base->id }}">{{ $base->name }}</option>@endforeach</select>
        </label>
        <label class="field"><span>Fecha</span><input type="date" name="purchased_at" required value="{{ now()->toDateString() }}"></label>
        <button class="btn btn-dark">Importar borrador</button>
    </div>
</form>
@endif
@endsection
